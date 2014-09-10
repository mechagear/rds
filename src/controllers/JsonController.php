<?php
class JsonController extends Controller
{
    const LAST_PACKAGE_REMOVE_CALL_TIME_KEY = 'RDS::actionGetProjectBuildsToDelete::last_call_time';
//    public function beforeAction()
//    {
//        //an: Специально эмулируем ситуацию, что сервер может иногда не работать
//        if (rand(1, 3) == 1) {
//            vardumpd("Server down :)");
//        }
//
//        return true;
//    }
    public function actionGetBuildTasks($worker)
    {
        $worker = Worker::model()->findByAttributes(array('worker_name' => $worker));
        if (!$worker) {
            throw new CHttpException(404, 'unknown worker');
        }

        $c = new CDbCriteria(array(
            'with' => array('project', 'releaseRequest'),
        ));
        $c->compare('build_release_request_obj_id', '>=0');

        $task = \Build::model()->findByAttributes(array(
            'build_worker_obj_id' => $worker->obj_id,
            'build_status' => Build::STATUS_NEW,
        ), $c);

        if ($task) {

            //an: ищем предыдущую успешную сборку, что бы узнать какие новые тикеты были закомичены
            $c = new CDbCriteria();
            $c->compare('rr_build_version', '<'.$task->releaseRequest->rr_build_version);
            $c->compare('rr_status', ReleaseRequest::getInstalledStatuses());
            $c->compare('rr_project_obj_id', $task->releaseRequest->rr_project_obj_id);
            $c->order = 'rr_build_version desc';
            $lastSuccess = ReleaseRequest::model()->find($c);

            $result = array(
                'id' => $task->obj_id,
                'project' => $task->project->project_name,
                'version' => $task->releaseRequest->rr_build_version,
                'release' => $task->releaseRequest->rr_release_version,
                'lastBuildTag' => $lastSuccess ? $lastSuccess->project->project_name.'-'.$lastSuccess->rr_build_version : null,
            );
        } else {
            $result = array();
        }

        echo json_encode($result);
    }

    public function actionSendBuildPatch($project, $version, $output)
    {
        if (!$Project = Project::model()->findByAttributes(['project_name' => $project])) {
            throw new CHttpException(404, "Project $project not found");
        }

        /** @var $releaseRequest ReleaseRequest */
        $releaseRequest = \ReleaseRequest::model()->findByAttributes([
            'rr_project_obj_id' => $Project->obj_id,
            'rr_build_version' => $version,
        ]);

        if (!$releaseRequest) {
            throw new CHttpException(404, "Release request of project=$project, version=$version not found");
        }

        $lines = explode("\n", str_replace("\r", "", $output));

        $tickets = [];
        foreach ($lines as $line) {
            if (preg_match('~^\s*(?<hash>\w+)\|(?<comment>.*)\|/(?<author>.*?)/$~', $line, $matches)) {
                $commit = new JiraCommit();
                if (preg_match_all('~#(WT\w-\d+)~', $matches['comment'], $ans)) {
                    foreach ($ans[1] as $val2) {
                        $tickets[] = $val2;
                        $commit->attributes = [
                            'jira_commit_build_tag' => $releaseRequest->getBuildTag(),
                            'jira_commit_hash' => $matches['hash'],
                            'jira_commit_author' => $matches['author'],
                            'jira_commit_comment' => $matches['comment'],
                            'jira_commit_ticket' => $val2,
                            'jira_commit_project' => explode('-', $val2)[0],
                        ];

                        $commit->save();
                    }
                }
            }
        }
        $tickets = array_unique($tickets);
        $releaseRequest->rr_tickets_count += count($tickets);
        $releaseRequest->save();

        echo json_encode(['ok' => true]);
    }

    public function actionSendMigrationCount($taskId, $count)
    {
        /** @var $build Build */
        $build = Build::model()->findByPk($taskId);
        if (!$build) {
            throw new CHttpException(404, 'Build not found');
        }
        $releaseRequest = $build->releaseRequest;
        $releaseRequest->rr_new_migration_count = $count;

        $result = array('ok' => $releaseRequest->save());

        echo json_encode($result);
    }

    public function actionSendCronConfig()
    {
        /** @var $build Build */
        $build = Build::model()->findByPk($_POST['taskId']);
        if (!$build) {
            throw new CHttpException(404, 'Build not found');
        }
        $releaseRequest = $build->releaseRequest;

        $releaseRequest->rr_cron_config = $_POST['text'];

        $result = array('ok' => $releaseRequest->save(false));

        echo json_encode($result);

    }

    public function actionSendMigrations($project, $version, array $migrations = array(), $type = 'pre')
    {
        /** @var $project Project */
        $project = Project::model()->findByAttributes(['project_name' => $project]);
        if (!$project) {
            throw new CHttpException(404, 'Project not found');
        }
        $releaseRequest = ReleaseRequest::model()->findByAttributes(array(
            'rr_project_obj_id' => $project->obj_id,
            'rr_build_version' => $version,
        ));
        if (!$releaseRequest) {
            throw new CHttpException(404, 'Release request not found');
        }
        if ($type == 'pre') {
            $releaseRequest->rr_new_migration_count = count($migrations);
            $releaseRequest->rr_new_migrations = json_encode($migrations);
        } else {
            $releaseRequest->rr_new_post_migrations = json_encode($migrations);
        }

        $result = array('ok' => $releaseRequest->save(false));

        echo json_encode($result);
    }

    public function actionGetKillTask($worker)
    {
        $worker = Worker::model()->findByAttributes(array('worker_name' => $worker));
        if (!$worker) {
            throw new CHttpException(404, 'unknown worker');
        }

        $result = array();

        $c = new CDbCriteria(array(
            'with' => array('project', 'project.project2workers', 'builds'),
        ));
        $c->compare('project2workers.worker_obj_id', $worker->obj_id);
        $c->compare('rr_status', array(\ReleaseRequest::STATUS_CANCELLING));
        $c->compare('build_status', array(\Build::STATUS_BUILDING, \Build::STATUS_BUILT));
        $task = \ReleaseRequest::model()->find($c);
        if ($task) {
            $result = array(
                'id' => $task->builds[0]->obj_id,
                'project' => $task->project->project_name,
                'use_status' => \ReleaseRequest::STATUS_USED,
            );
        }
        echo json_encode($result);

    }

    public function actionGetUseTasks($worker)
    {
        $worker = Worker::model()->findByAttributes(array('worker_name' => $worker));
        if (!$worker) {
            throw new CHttpException(404, 'unknown worker');
        }

        $result = array();

        //an: Смотрим есть ли что, что нужно откатывать к старой версии
        $c = new CDbCriteria(array(
            'with' => array('project', 'project.project2workers', 'builds'),
        ));
        $c->compare('project2workers.worker_obj_id', $worker->obj_id);
        $c->compare('rr_status', array(\ReleaseRequest::STATUS_USING, \ReleaseRequest::STATUS_USED_ATTEMPT));
        $c->compare('build_status', \Build::STATUS_USED);
        $c->compare('rr_revert_after_time', "<=".date("r"));
        $task = \ReleaseRequest::model()->find($c);

        if ($task) {
            $result = array(
                'id' => $task->obj_id,
                'project' => $task->project->project_name,
                'version' => $task->rr_old_version,
                'use_status' => \ReleaseRequest::STATUS_USED,
            );
        } else {
            //an: Если ничего нету - тогда смотрим какую новую версию нужно накатить
            $c = new CDbCriteria(array(
                'with' => array('project', 'project.project2workers', 'builds'),
            ));
            $c->compare('project2workers.worker_obj_id', $worker->obj_id);
            $c->compare('rr_status', \ReleaseRequest::STATUS_USING);
            $c->compare('build_status', \Build::STATUS_INSTALLED);

            $task = \ReleaseRequest::model()->find($c);

            if ($task) {
                $result = array(
                    'id' => $task->obj_id,
                    'project' => $task->project->project_name,
                    'version' => $task->rr_build_version,
                    'use_status' =>
                        //an: Для отката не используем автокат
                        $task->rr_build_version > $task->project->project_current_version
                            ? \ReleaseRequest::STATUS_USED_ATTEMPT
                            : \ReleaseRequest::STATUS_USED,
                );
            }
        }
        echo json_encode($result);
    }

    public function actionGetMigrationTask($worker)
    {
        $worker = Worker::model()->findByAttributes(array('worker_name' => $worker));
        if (!$worker) {
            throw new CHttpException(404, 'unknown worker');
        }

        $result = array();

        foreach (array('pre' => 'rr_migration_status', 'post' => 'rr_post_migration_status') as $type => $field) {
            //an: Смотрим есть ли что, что нужно откатывать к старой версии
            $releaseRequest = ReleaseRequest::model()->findByAttributes(array(
                $field => \ReleaseRequest::MIGRATION_STATUS_UPDATING,
            ));

            if ($releaseRequest) {
                $result = array(
                    'project' => $releaseRequest->project->project_name,
                    'version' => $releaseRequest->rr_build_version,
                    'type' => $type,
                );
                break;
            }
        }


        echo json_encode($result);
    }

    public function actionSendMigrationStatus($project, $version, $type, $status)
    {
        $transaction = ReleaseRequest::model()->getDbConnection()->beginTransaction();
        $projectObj = Project::model()->findByAttributes(array('project_name' => $project));

        if (!$projectObj) {
            throw new CHttpException(404, 'unknown project');
        }
        $releaseRequest = ReleaseRequest::model()->findByAttributes(array('rr_build_version' => $version, 'rr_project_obj_id' => $projectObj->obj_id));

        if (!$releaseRequest) {
            throw new CHttpException(404, 'unknown release request');
        }

        if ($type == 'pre') {
            $releaseRequest->rr_migration_status = $status;

            if ($status == \ReleaseRequest::MIGRATION_STATUS_UP) {
                $releaseRequest->rr_new_migration_count = 0;
                $c = new CDbCriteria();
                $c->compare('rr_build_version', "<=$version");
                $c->compare('rr_project_obj_id', $projectObj->obj_id);

                ReleaseRequest::model()->updateAll(array('rr_migration_status' => $status, 'rr_new_migration_count' => 0), $c);
            }
        } else {
            $releaseRequest->rr_post_migration_status = $status;

            if ($status == \ReleaseRequest::MIGRATION_STATUS_UP) {
                $c = new CDbCriteria();
                $c->compare('rr_build_version', "<=$version");
                $c->compare('rr_project_obj_id', $projectObj->obj_id);

                ReleaseRequest::model()->updateAll(array('rr_migration_status' => $status), $c);
            }
        }

        $text = json_encode(array('ok' => $releaseRequest->save()));
        $transaction->commit();

        echo $text;
    }

    public function actionSetOldVersion($id, $version)
    {
        $releaseRequest = \ReleaseRequest::model()->findByPk($id);
        if (!$releaseRequest) {
            throw new CHttpException(404, 'not found');
        }
        if (!$releaseRequest->rr_old_version) {
            $releaseRequest->rr_old_version = $version;
            $result = array('ok' => $releaseRequest->save());
        } else {
            $result = array('ok' => true);
        }
        echo json_encode($result);
    }

    public function actionSetUseError($id, $text)
    {
        $releaseRequest = \ReleaseRequest::model()->findByPk($id);
        if (!$releaseRequest) {
            throw new CHttpException(404, 'not found');
        }
        $releaseRequest->rr_use_text = $text;
        $releaseRequest->rr_status = \ReleaseRequest::STATUS_FAILED;
        $result = array('ok' => $releaseRequest->save());

        echo json_encode($result);
    }

    public function actionSetUsedVersion($worker, $project, $version, $status)
    {
        $worker = Worker::model()->findByAttributes(array('worker_name' => $worker));
        if (!$worker) {
            throw new CHttpException(404, 'unknown worker');
        }

        if (!in_array($status, array(\ReleaseRequest::STATUS_USED, \ReleaseRequest::STATUS_USED_ATTEMPT))) {
            throw new CHttpException(503, 'Forbidden, invalid status');
        }

        $project = \Project::model()->findByAttributes(array('project_name' => $project));
        if (!$project) {
            throw new CHttpException(404, 'Project not found');
        }

        $transaction = $project->dbConnection->beginTransaction();

        $releaseRequest = \ReleaseRequest::model()->findByAttributes(array(
            'rr_build_version' => $version,
            'rr_project_obj_id' => $project->obj_id,
        ));

        $builds = \Build::model()->findAllByAttributes(array(
            'build_project_obj_id' => $project->obj_id,
            'build_worker_obj_id' => $worker->obj_id,
            'build_status' => Build::STATUS_USED,
        ));

        foreach ($builds as $build) {
            $build->build_status = Build::STATUS_INSTALLED;
            $build->save();
        }

        if ($releaseRequest) {
            $build = \Build::model()->findByAttributes(array(
                'build_project_obj_id' => $project->obj_id,
                'build_worker_obj_id' => $worker->obj_id,
                'build_release_request_obj_id' => $releaseRequest->obj_id,
            ));
            $build->build_status = Build::STATUS_USED;
            $build->save();
        }

        $p2w = Project2worker::model()->findByAttributes(array(
            'worker_obj_id' => $worker->obj_id,
            'project_obj_id' => $project->obj_id,
        ));
        if ($p2w) {
            $p2w->p2w_current_version = $version;
            $p2w->save();
        }
        $list = \Project2worker::model()->findAllByAttributes(array(
            'project_obj_id' => $project->obj_id,
        ));
        $ok = true;
        foreach ($list as $p2w) {
            if ($p2w->p2w_current_version != $version) {
                $ok = false;
                break;
            }
        }

        if ($ok) {
            $oldVersion = $project->project_current_version;
            $project->project_current_version = $version;
            $project->save(false);

            $oldUsed = \ReleaseRequest::model()->findByAttributes(array(
                'rr_status' => array(
                    \ReleaseRequest::STATUS_USED,
                    \ReleaseRequest::STATUS_USED_ATTEMPT,
                ),
                'rr_project_obj_id' => $project->obj_id,
            ));

            if ($oldUsed) {
                $oldUsed->rr_status = \ReleaseRequest::STATUS_OLD;
                $oldUsed->rr_last_time_on_prod = date("r");
                $oldUsed->rr_revert_after_time = null;
                $oldUsed->save(false);
            }

            if ($releaseRequest) {
                $releaseRequest->rr_status = $status;
                $releaseRequest->save(false);
            }

            if ($oldVersion < $version) {
                $title = "Deployed $project->project_name v.$version";
            } else {
                $title = "Reverted $project->project_name v.$version";
            }
            Yii::app()->whotrades->{'getMailingSystemFactory.getPhpLogsNotificationModel.sendReleaseReleased'}($project->project_name, $version);
            foreach (explode(",", \Yii::app()->params['notify']['use']['phones']) as $phone) {
                if (!$phone) continue;
                Yii::app()->whotrades->{'getFinamTenderSystemFactory.getSmsSender.sendSms'}($phone, $title);
            }
        }


        $transaction->commit();

        echo json_encode(array('ok' => true));
    }

    public function actionGetCurrentStatus($id)
    {
        if (!$releaseRequest = \ReleaseRequest::model()->findByPk($id)) {
            throw new CHttpException(404, 'Project not found');
        }

        echo json_encode(array(
            'id' => $id,
            'status' => $releaseRequest->rr_status,
            'version' => $releaseRequest->rr_build_version,
        ));

    }

    public function actionSendStatus()
    {
        /** @var $request CHttpRequest*/
        $request = Yii::app()->request;
        $taskId = $request->getPost('taskId');
        $status = $request->getPost('status');
        $version = $request->getPost('version');
        $attach = $request->getPost('attach');

        /** @var $build Build*/
        $build = Build::model()->findByPk($taskId);
        if (!$build) {
            throw new CHttpException(404, 'Build not found');
        }
        $project = $build->project;

        $build->build_status = $status;
        if ($attach) {
            $build->build_attach = $attach;
        }
        if ($version) {
            $build->build_version = $version;
        }

        $build->save();

        switch ($status) {
            case Build::STATUS_INSTALLED:
                if ($build->releaseRequest->countNotFinishedBuilds() == 0) {
                    $builds = $build->releaseRequest->builds;
                    $build->releaseRequest->rr_status = \ReleaseRequest::STATUS_INSTALLED;
                    $build->releaseRequest->rr_built_time = date("r");
                    $build->releaseRequest->save();
                    $title = "Success installed $project->project_name v.$version";
                    $text = "Проект $project->project_name был собран и разложен по серверам.<br />";
                    foreach ($builds as $val) {
                        $text .= "<a href='".$this->createAbsoluteUrl('build/view', array('id' => $val->obj_id))."'>Подробнее {$val->worker->worker_name} v.{$val->build_version}</a><br />";
                    }

                    foreach (Yii::app()->params['jiraProjects'] as $jiraProject) {
                        $jiraVersion = new JiraCreateVersion();
                        $jiraVersion->attributes = [
                            'jira_name' => $project->project_name."-".$build->releaseRequest->rr_build_version,
                            'jira_description' => 'Сборка #'.$build->build_release_request_obj_id.', '.$build->releaseRequest->rr_user.' [auto]',
                            'jira_project' => $jiraProject,
                            'jira_archived' => false,
                            'jira_released' => false,
                        ];

                        $jiraVersion->save(false);
                    }

                    Yii::app()->whotrades->{'getMailingSystemFactory.getPhpLogsNotificationModel.sendReleaseRejectCustomNotification'}('success', $title, $version, $text);
                    foreach (explode(",", \Yii::app()->params['notify']['status']['phones']) as $phone) {
                        if (!$phone) continue;
                        Yii::app()->whotrades->{'getFinamTenderSystemFactory.getSmsSender.sendSms'}($phone, $title);
                    }
                }
                break;
            case Build::STATUS_FAILED:
                $title = "Failed to install $project->project_name";
                $text = "Проект $project->project_name не удалось собрать. <a href='".$this->createAbsoluteUrl('build/view', array('id' => $build->obj_id))."'>Подробнее</a>";

                Yii::app()->whotrades->{'getMailingSystemFactory.getPhpLogsNotificationModel.sendReleaseRejectCustomNotification'}('success', $title, $version, $text);
                foreach (explode(",", \Yii::app()->params['notify']['status']['phones']) as $phone) {
                    if (!$phone) continue;
                    Yii::app()->whotrades->{'getFinamTenderSystemFactory.getSmsSender.sendSms'}($phone, $title);
                }
                break;
            case Build::STATUS_CANCELLED:
                $title = "Failed to install $project->project_name";
                $text = "Проект $project->project_name не удалось собрать. <a href='".$this->createAbsoluteUrl('build/view', array('id' => $build->obj_id))."'>Подробнее</a>";

                $c = new CDbCriteria(array(
                    'with' => array('project', 'project.project2workers', 'builds'),
                ));
                $c->compare('project2workers.worker_obj_id', $build->build_worker_obj_id);
                $c->compare('rr_status', array(\ReleaseRequest::STATUS_CANCELLING));
                $c->compare('build_status', array(\Build::STATUS_BUILDING, \Build::STATUS_BUILT));
                $task = \ReleaseRequest::model()->find($c);
                if (!$task) {
                    $releaseRequest = $build->releaseRequest;
                    $releaseRequest->rr_status = \ReleaseRequest::STATUS_CANCELLED;
                    $releaseRequest->save();
                }

                Yii::app()->whotrades->{'getMailingSystemFactory.getPhpLogsNotificationModel.sendReleaseRejectCustomNotification'}('success', $title, $version, $text);
                foreach (explode(",", \Yii::app()->params['notify']['status']['phones']) as $phone) {
                    if (!$phone) continue;
                    Yii::app()->whotrades->{'getFinamTenderSystemFactory.getSmsSender.sendSms'}($phone, $title);
                }
                break;
        }

        echo json_encode(array("success" => true));
    }

    public function actionGetRejects($projectName)
    {
        $project = Project::model()->findByAttributes(array('project_name' => $projectName));
        $result = array();
        if ($project) {
            $rejects = $project->releaseRejects;
            foreach ($rejects as $reject) {
                $result[] = array(
                    'created' => $reject->obj_created,
                    'user' => $reject->rr_user,
                    'comment' => $reject->rr_comment,
                );
            }
        }

        echo json_encode($result);
    }

    public function actionGetProjects()
    {
        $projects = Project::model()->findAll();
        $result = array();
        foreach ($projects as $project) {
            /** @var $project Project */
            $result[] = array(
                'name' => $project->project_name,
                'current_version' => $project->project_current_version,
            );
        }

        echo json_encode($result);
    }

    /**
     * Метод, который анализирует сборки проектов на возможность их удаления из системы
     */
    public function actionGetProjectBuildsToDelete()
    {
        $builds = isset($_REQUEST['builds']) ? $_REQUEST['builds'] : [];

        $result = array();
        foreach ($builds as $build) {
            if (!preg_match('~\d{2,3}\.\d\d\.\d+\.\d+~', $build['version']) && !preg_match('~2014\.\d{2,3}\.\d\d\.\d+\.\d+~', $build['version'])) {
                //an: неизвестный формат версии, лучше не будем удалять :) фиг его знает что это
                continue;
            }
            /** @var $project Project */
            $project = Project::model()->findByAttributes(['project_name' => $build['project']]);
            if (!$project) {
                //an: непонятно чтои зачем нам прислали, лучше не будем удалять
                continue;
            }

            if ($build['version'] == $project->project_current_version) {
                //an: Ну никак нельзя удалять ту версию, что сейчас зарелижена
                continue;
            }

            $releaseRequest = \ReleaseRequest::model()->findByAttributes([
                'rr_project_obj_id' => $project->obj_id,
                'rr_build_version' => $build['version'],
            ]);

            if ($releaseRequest && $releaseRequest->rr_last_time_on_prod > date('Y-m-d', strtotime('-1 month'))) {
                //an: Не удаляем те билды, что были на проде меньше месяца назад
                continue;
            }

            $numbersOfTest = explode(".", $build['version']);
            if ($numbersOfTest[0] == 2014) array_shift($numbersOfTest);

            $numbersOfCurrent = explode(".", $project->project_current_version);
            if ($numbersOfCurrent[0] == 2014) array_shift($numbersOfCurrent);

            if ($numbersOfCurrent[0] - 2 > $numbersOfTest[0]) {
                //an: если релиз отличается на 2 и больше от того что сейчас на проде, тогда удаляем
                $c = new CDbCriteria();
                $c->compare('rr_project_obj_id', $project->obj_id);
                $c->compare('rr_build_version', '>'.$build['version']);
                $c->compare('rr_build_version', '<'.$project->project_current_version);
                $count = \ReleaseRequest::model()->count($c);

                if ($count > 2) {
                    //an: Нужно наличие минимум 2 версий от текущей, что бы было куда откатываться
                    $result[] = $build;
                }
            }
        }

        CoreLight::getInstance()->getServiceBaseCacheKvdpp()->set(self::LAST_PACKAGE_REMOVE_CALL_TIME_KEY, time());

        echo json_encode($result);
    }

    public function actionRemoveReleaseRequest($projectName, $version)
    {
        $project = Project::model()->findByAttributes(['project_name' => $projectName]);
        if (!$project) {
            throw new CHttpException(404, 'Build not found');
        }

        $rr = ReleaseRequest::model()->findByAttributes([
            'rr_project_obj_id' => $project->obj_id,
            'rr_build_version' => $version,
        ]);

        if ($rr) {
            $rr->delete();
        }

        echo json_encode(array('ok' => true));
    }
}

