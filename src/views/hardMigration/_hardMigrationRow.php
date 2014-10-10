<?php
/** @var $model HardMigration */
return array(
    [
        'name' => 'releaseRequest.rr_project_obj_id',
        'value' => function(HardMigration $migration){
            return $migration->releaseRequest->project->project_name;
        },
        'filter' => CHtml::activeDropDownList($model, 'project_obj_id', Project::model()->forList()),
    ],
    [
        'name' => 'releaseRequest.rr_build_version',
        'filter' => CHtml::activeTelField($model, 'build_version'),
    ],
    'obj_created',
    'migration_release_request_obj_id',
    [
        'name' => 'migration_name',
        'value' => function(HardMigration $migration){
            return  "<a href='http://sources:8060/browse/migration-{$migration->releaseRequest->project->project_name}/hard/$migration->migration_name.php?hb=true' target='_blank' title='Посмотреть исходный код миграции'>$migration->migration_name</a><br />";
        },
        'type' => 'html',
    ],
    [
        'name' => 'migration_ticket',
        'value' => function(HardMigration $migration){
            return "<a href='http://jira/browse/$migration->migration_ticket' target='_blank' title='Перейти в JIRA'>$migration->migration_ticket</a>";
        },
        'type' => 'html',
    ],
    [
        'name' => 'migration_status',
        'value' => function(HardMigration $migration){
            echo "<b>$migration->migration_status</b><br />";
            if ($migration->migration_log) {
                echo "<a href='".$this->createUrl('/hardMigration/log', ['id' => $migration->obj_id])."'>LOG</a>";
            }
        },
        'type' => 'html',
    ],
    'migration_retry_count',
    [
        'name' => 'migration_progress',
        'value' => function(HardMigration $migration){
            if (!in_array($migration->migration_status, [\HardMigration::MIGRATION_STATUS_IN_PROGRESS, \HardMigration::MIGRATION_STATUS_PAUSED])) {
                return false;
            }
            return '<div class="progress progress-'.$migration->obj_id.'" style="margin: 0; width: 250px;">
                        <div class="bar" role="progressbar"style="width: '.$migration->migration_progress.'%;white-space:nowrap; color:#FFA500; padding-left: 5px">
                            <b>'.sprintf("%.2f", $migration->migration_progress).'%:</b> '.$migration->migration_progress_action.'
                        </div>
                    </div>';
        },
        'type' => 'html',
    ],
    [
        'name' => 'migration_progress_action',
        'value' => function(HardMigration $migration){
            echo "<div class='progress-action-$migration->obj_id'>$migration->migration_progress_action</div>";
        },
        'type' => 'html',
    ],

    array(
        'class'=>'CButtonColumn',
        'template' => '{start} {stop} {pause} {restart} {resume}',
        'buttons' => [
            'start' => [
                'visible' => '$data->canBeStarted()',
                'url' => 'Yii::app()->controller->createUrl("/hardMigration/start",array("id"=>$data->primaryKey))',
                'label' => '<span class="icon-play" style="color: #32cd32"></span>',
                'options' => [
                    'title' => 'Запустить миграцию',
                ],
            ],
            'stop' => [
                'visible' => '$data->canBeStopped()',
                'url' => 'Yii::app()->controller->createUrl("/hardMigration/stop",array("id"=>$data->primaryKey))',
                'label' => '<span class="icon-stop" style="color: #32cd32"></span>',
                'options' => [
                    'title' => 'Остановить миграцию',
                ],
            ],
            'pause' => [
                'visible' => '$data->canBePaused()',
                'url' => 'Yii::app()->controller->createUrl("/hardMigration/pause",array("id"=>$data->primaryKey))',
                'label' => '<span class="icon-pause" style="color: #32cd32"></span>',
                'options' => [
                    'title' => 'Поставить на паузу',
                ],
            ],
            'resume' => [
                'visible' => '$data->canBeResumed()',
                'url' => 'Yii::app()->controller->createUrl("/hardMigration/resume",array("id"=>$data->primaryKey))',
                'label' => '<span class="icon-play" style="color: #32cd32"></span>',
                'options' => [
                    'title' => 'Запустить миграцию',
                ],
            ],
            'restart' => [
                'visible' => '$data->canBeRestarted()',
                'url' => 'Yii::app()->controller->createUrl("/hardMigration/restart",array("id"=>$data->primaryKey))',
                'label' => '<span class="icon-play" style="color: #32cd32"></span>',
                'options' => [
                    'title' => 'Перезапустить миграцию',
                ],
            ],
        ],
    ),
);