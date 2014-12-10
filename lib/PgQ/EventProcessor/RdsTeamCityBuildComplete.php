<?php
/**
 * Консьюмер запрашивает сборку в teamcity всех наших репозиториев в фичевой ветке и складывает в таблицу
 *
 * @author Artem Naumenko
 * @example sphp dev/services/rds/misc/tools/db/pgq/process.php --event-processor=RdsTeamCityBuildComplete  --queue-name=rds_teamcity_build_complete --consumer-name=rds_teamcity_build_complete_consumer --partition=1  --dsn-name=DSN_DB4  --strategy=simple -vvv process_queue
 */

class PgQ_EventProcessor_RdsTeamCityBuildComplete extends PgQ\EventProcessor\EventProcessorBase
{
    public function processEvent(PgQ_Event $event)
    {
        $this->debugLogger->debug("Processing event ".json_encode($event->getData()));
        $id = $event->getData()['tbc_build_id'];
        $branch = $event->getData()['tbc_branch'];
        $buildTypeId = $event->getData()['tbc_build_type_id'];
        /** @var $debugLogger \ServiceBase_IDebugLogger */
        $debugLogger = Yii::app()->debugLogger;

        //an: Так как мы не можем по-нормальному связать задачу на сборку с самой сборкой, то ориентируемся просто на последний запрос билда с этой веткой и нужным типом
        $c = new CDbCriteria();
        $c->order = 'obj_id desc';
        $c->compare('tb_build_type_id', $buildTypeId);
        $c->compare('tb_branch', $branch);
        $c->limit = 1;

        /** @var $teamCityBuild TeamCityBuild */
        $teamCityBuild = TeamcityBuild::model()->find($c);

        $debugLogger->message("Received message of completion build #$id (http://ci.whotrades.net:8111/viewLog.html?buildId=$id)");

        if (empty($teamCityBuild)) {
            $debugLogger->message("Build not found at database, skip message");
            $this->debugLogger->error("Build with branch=$branch and buildType=$buildTypeId not found");
            return;
        }

        $teamcity = new \TeamcityClient\WtTeamCityClient();
        $info = $teamcity->getBuildInfo($id);
        if ($info['state'] != 'finished') {
            $debugLogger->message("Build is not finished yet, retry message in 5 seconds");
            $event->retry(5);
            return;
        }

        $ticket = $teamCityBuild->teamCityRunTest->jiraFeature->jf_ticket;
        $jiraApi = new JiraApi($debugLogger);
        //an: Получаем информацию о тикете и заодно убеждаемся что жира работает
        $ticketInfo = $jiraApi->getTicketInfo($ticket);

        $teamCityBuild->tb_status = $info['status'] == 'SUCCESS' ? TeamCityBuild::STATUS_SUCCESS :  TeamCityBuild::STATUS_FAILED;
        $teamCityBuild->save(false);

        //an: Считаем сколько ещё осталось незавершенных билдов
        $count = TeamCityBuild::model()->countByAttributes([
            'tb_run_test_obj_id' => $teamCityBuild->tb_run_test_obj_id,
            'tb_status' => TeamCityBuild::STATUS_QUEUED
        ]);

        $debugLogger->message("Queued build left: $count");

        if ($count == 0) {
            //an: все билды отработали, анализируем все ли они успешные
            $failedBuilds = TeamCityBuild::model()->findAllByAttributes([
                'tb_run_test_obj_id' => $teamCityBuild->tb_run_test_obj_id,
                'tb_status' => TeamCityBuild::STATUS_FAILED
            ]);

            if (empty($failedBuilds)) {
                //an: все успешные, двигаем задачу на кодревью
                $debugLogger->message("All build are success, move ticket to code review");
                $jiraApi->transitionTicket($ticketInfo, \Jira\Transition::FINISH_INTEGRATION_TESTING);
            } else {
                //an: не все отработало успешно, пишем об этом в жире и отправляем задачу на доработку
                $debugLogger->message("Not all build are success, move ticket back to developer");
                $jiraApi->transitionTicket($ticketInfo, \Jira\Transition::FAILED_INTEGRATION_TESTING);

                $comment = "Не все тесты прошли успешно. Неуспешные тесты: ";
                foreach ($failedBuilds as $build) {
                    /** @var $build TeamCityBuild */
                    $comment .= "\n{$build->tb_url}";
                }

                $jiraApi->addComment($ticket, $comment);
            }
        }
    }
}