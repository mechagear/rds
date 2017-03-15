<?php
/**
 * @author Artem Naumenko
 * Настройки cron jobs проекта rds
 */

use \Cronjob\ConfigGenerator;
use \Cronjob\ConfigGenerator\Comment;
use \Cronjob\ConfigGenerator\MultiCronCommand;
use \Cronjob\ConfigGenerator\CronCommand;
use \Cronjob\ConfigGenerator\MultiCommandToCron;

/** @example sphp dev/services/rds/misc/tools/runner.php --tool=CodeGenerate_CronjobGenerator -vv --project=service-rds --env=prod --server=1 */
class ServiceRdsProdTL1
{
    /**
     * @return array
     */
    public function getCronConfigRows()
    {
        $allCommands = $this->getAllCommands();

        $allCommands = new MultiCronCommand($allCommands);

        $rows = $allCommands->getCronConfigRows();

        return array_merge($this->getEnv(), $rows);
    }

    protected function getAllCommands()
    {
        return [
            new Comment("JIRA integration"),
            new MultiCommandToCron(
                \PgQ_EventProcessor_RdsJiraCreateVersion::getPgqConsumer('rds_jira_create_version', 'rds_jira_create_version_consumer', 'simple', 'DSN_DB4', 1, [], 3),
                '* * * * * *',
                'rds_jira_create_version'
            ),
            new MultiCommandToCron(
                \PgQ_EventProcessor_PhpCsStashIntegration::getPgqConsumer('rds_stash_phpcs_integration', 'rds_stash_phpcs_integration_consumer', 'simple', 'DSN_DB4', 1, [], 3),
                '* * * * * *',
                'rds_stash_phpcs_integration'
            ),
            new MultiCommandToCron(
                \PgQ_EventProcessor_RdsJiraCommit::getPgqConsumer('rds_jira_commit', 'rds_jira_commit_consumer', 'simple', 'DSN_DB4', 1, [], 3),
                '* * * * * *',
                'rds_jira_commit'
            ),
            new MultiCommandToCron(
                \PgQ_EventProcessor_RdsJiraTicketStatus::getPgqConsumer('rds_jira_commit', 'rds_jira_ticket_status_consumer', 'simple', 'DSN_DB4', 1, [], 3),
                '* * * * * *',
                'rds_jira_commit-ticket_status'
            ),
            new MultiCommandToCron(
                \PgQ_EventProcessor_RdsJiraUse::getPgqConsumer('rds_jira_use', 'rds_jira_use_consumer', 'simple', 'DSN_DB4', 1, [], 3),
                '* * * * * *',
                'rds_jira_use'
            ),
            new MultiCommandToCron(
                \PgQ_EventProcessor_RdsJiraUseExternalNotifier::getPgqConsumer('rds_jira_use', 'rds_jira_use_external_notifier_consumer', 'simple', 'DSN_DB4', 1, [], 3),
                '* * * * * *',
                'rds_jira_use_external_notifier_consumer'
            ),
            new MultiCommandToCron(
                \PgQ_EventProcessor_SentryAfterUseErrorsNotification::getPgqConsumer(
                    'rds_jira_use',
                    'sentry_after_use_errors_notification_consumer',
                    'simple',
                    'DSN_DB4',
                    1,
                    [],
                    3
                ),
                '* * * * * *',
                'rds_jira_use_sentry_after_use_errors_notification_consumer'
            ),
            new MultiCommandToCron(
                \PgQ_EventProcessor_JiraMoveTicket::getPgqConsumer('rds_jira_move_ticket', 'rds_jira_move_ticket_consumer', 'simple', 'DSN_DB4', 1, [], 3),
                '* * * * * *',
                'rds_jira_move_ticket'
            ),
            new MultiCommandToCron(
                \PgQ_EventProcessor_JiraAsyncRpc::getPgqConsumer('rds_jira_async_rpc', 'rds_jira_async_rpc_consumer', 'simple', 'DSN_DB4', 1, [], 3),
                '* * * * * *',
                'rds_jira_async_rpc'
            ),

            new CronCommand(Cronjob_Tool_Jira_FixVersionsRelease::getToolCommand([], $verbosity = 1), '46 10 * * * *', 'rds_jira_fix_versions_release'),
            new CronCommand(Cronjob_Tool_Jira_MergeTasks::getToolCommand(['--max-duration=60'], $verbosity = 1), '*/6 * * * * *', 'rds_jira_merge_tasks'),
            new CronCommand(Cronjob_Tool_Jira_CloseFeatures::getToolCommand([], $verbosity = 1), '32 10 * * * *', 'rds_jira_close_features'),
            new CronCommand(Cronjob_Tool_Jira_CodeReview::getToolCommand([], $verbosity = 3), '25 * * * * *', 'rds_jira_code_review'),
            new CronCommand(Cronjob_Tool_Jira_HardMigrationNotifier::getToolCommand([], $verbosity = 1), '21 10 4 * * *', 'rds_jira_hard_migration_notifier'),

            new Comment("TeamCity integration"),
            new MultiCommandToCron(
                \PgQ_EventProcessor_RdsTeamCityRunTest::getPgqConsumer('rds_teamcity_run_test', 'rds_teamcity_run_test_consumer', 'simple', 'DSN_DB4', 1, [], 3),
                '* * * * * *',
                'rds_teamcity_run_test'
            ),
            new MultiCommandToCron(
                \PgQ_EventProcessor_RdsTeamCityBuildComplete::getPgqConsumer('rds_teamcity_build_complete', 'rds_teamcity_build_complete_consumer', 'simple', 'DSN_DB4', 1, [], 3),
                '* * * * * *',
                'rds_teamcity_build_complete'
            ),
            new CronCommand(Cronjob_Tool_TeamCityCheckQueuedTasks::getToolCommand([], $verbosity = 1), '28 * * * * *', 'rds_team_city_check_queued_tasks'),

            new Comment("Stash integration"),
            new MultiCommandToCron(
                \PgQ_EventProcessor_ProcessCreatePullRequest::getPgqConsumer('rds_create_pull_request', 'rds_create_pull_request_consumer', 'simple', 'DSN_DB4', 1, [], 3),
                '* * * * * *',
                'rds_create_pull_request'
            ),
            new CronCommand(Cronjob_Tool_BitBucket2Graphite::getToolCommand([], $verbosity = 1), '0 * * * * *', 'rds_bitbucket_stat2graphite'),

            new Comment("Deployment"),
            new CronCommand(Cronjob_Tool_AsyncReader_Deploy::getToolCommand(['--max-duration=60'], $verbosity = 1), '* * * * * *', 'rds_async_reader_deploy'),
            new CronCommand(Cronjob_Tool_AsyncReader_Merge::getToolCommand(['--max-duration=60'], $verbosity = 1), '* * * * * *', 'rds_async_reader_merge'),

            new Comment("Hard migrations"),
            new CronCommand(Cronjob_Tool_AsyncReader_HardMigration::getToolCommand(['--max-duration=60'], $verbosity = 1), '* * * * * *', 'rds_async_reader_hard_migration-prod'),
            new CronCommand(Cronjob_Tool_AsyncReader_HardMigrationProgress::getToolCommand(['--max-duration=60'], $verbosity = 1), '* * * * * *', 'rds_hard_migration_progress'),
            new CronCommand(Cronjob_Tool_AsyncReader_HardMigrationLogChunk::getToolCommand(['--max-duration=60'], $verbosity = 1), '* * * * * *', 'rds_hard_migration_log_chunk'),

            new Comment("Maintenance tools"),
            new CronCommand(Cronjob_Tool_AsyncReader_MaintenanceTool::getToolCommand(['--max-duration=60'], $verbosity = 1), '* * * * * *', 'rds_maintenance_tool'),
            new CronCommand(
                Cronjob_Tool_MaintenanceToolRun::getToolCommand(['--tool-name=systemTest --env=main'], $verbosity = 1),
                '8 */10 * * * *',
                'rds_maintenance_tool_run-system'
            ),     // an: для проверки работоспособности системы запуска тулов

            new Comment("Управляющий тул (убивает другие тулы)"),
            new CronCommand(Cronjob_Tool_Maintenance_MasterTool::getToolCommand(['--max-duration=60'], $verbosity = 1), '* * * * * *', 'rds_master_tool'),

            new Comment("Лампа"),
            new CronCommand(Cronjob_Tool_RdsAlertStatus::getToolCommand([], $verbosity = 1), '*/20 * * * * *', 'rds_alert_status'),

            new Comment("Ротация логом тяжелых миграций"),
            new CronCommand(Cronjob_Tool_HardMigrationLogRotator::getToolCommand([], $verbosity = 1), '*/28 * * * * *', 'rds_hard_migration_log_rotator'),

            new Comment("Удаление старых веток из git"),
            new CronCommand(Cronjob_Tool_GitDropFeatureBranch::getToolCommand([], $verbosity = 3), '37 10 20 * * *', 'rds_git_drop_feature_branch'),

            new Comment("Уведомления о релизах"),
            new MultiCommandToCron(
                \PgQ_EventProcessor_RdsJiraNotificationQueue::getPgqConsumer('rds_jira_notification_queue', 'rds_jira_notification_queue_consumer', 'simple', 'DSN_DB4', 1, [], 3),
                '* * * * * *',
                'rds_jira_notification_queue'
            ),

            new Comment("Пересборка веток"),
            new CronCommand(Cronjob_Tool_Git_RebuildBranch::getToolCommand(['--branch=develop'], $verbosity = 3), '39 10 20 * * *', 'rds_rebuild_develop'),
            new CronCommand(Cronjob_Tool_Git_RebuildBranch::getToolCommand(['--branch=staging'], $verbosity = 3), '39 30 20 * * *', 'rds_rebuild_staging'),
        ];
    }

    protected function getEnv()
    {
        return [
            'MAILTO=adm+ny_cron@whotrades.org',
            'CRONJOB_TOOLS=/var/www/service-rds/misc/tools',
        ];
    }
}
