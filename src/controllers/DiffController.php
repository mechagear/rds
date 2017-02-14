<?php
namespace app\controllers;

use app\models\ProjectConfigHistory;
use app\models\ReleaseRequest;

class DiffController extends Controller
{
    public $pageTitle = 'Различия';

    public function actionIndex($id1, $id2)
    {
        /** @var $rr1 ReleaseRequest */
        $rr1 = ReleaseRequest::findByPk($id1);
        /** @var $rr2 ReleaseRequest */
        $rr2 = ReleaseRequest::findByPk($id2);

        $this->render('index', array(
            'projectName' => $rr1->project->project_name,
            'filename' => 'cron-wt.d',
            'newText' => $rr1->getCronConfigCleaned(),
            'newTitle' => "$rr1->rr_build_version - CURRENT VERSION",
            'currentText' => $rr2->getCronConfigCleaned(),
            'currentTitle' => "$rr2->rr_build_version - NEW VERSION",
        ));
    }

    public function actionProject_config($id)
    {
        /** @var $rr1 ProjectConfigHistory */
        $rr1 = ProjectConfigHistory::findByPk($id);

        $c = new CDbCriteria();
        $c->compare('pch_project_obj_id', $rr1->pch_project_obj_id);
        $c->compare('pch_filename', $rr1->pch_filename);
        $c->compare('obj_id', "<$id");
        $c->order = 'obj_id desc';
        $c->limit = 1;

        /** @var $rr2 ProjectConfigHistory */
        $rr2 = ProjectConfigHistory::find($c);

        $this->render('index', array(
            'projectName' => $rr1->project->project_name,
            'filename' => $rr1->pch_filename,
            'newText' => $rr1->pch_config,
            'newTitle' => "Новая версия: ".date('d.m.Y H:i:s', strtotime($rr1->obj_created))." ".$rr1->pch_user,
            'currentText' => $rr2 ? $rr2->pch_config : "",
            'currentTitle' => "Старая версия: ". ($rr2 ? date('d.m.Y H:i:s', strtotime($rr2->obj_created))." ".$rr2->pch_user : ""),
        ));
    }
}
