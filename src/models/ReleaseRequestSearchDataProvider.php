<?php
namespace whotrades\rds\models;

use yii\data\ActiveDataProvider;

/**
 * Класс для отображения списка запросов на релиз на главной странице RDS
 * Кроме стандартных колонок ещё подтягивает дополнительные - к какому релизу откатываться, по сравнению с каким считать diff и т.д.
 */

class ReleaseRequestSearchDataProvider extends ActiveDataProvider
{
    private $additionalDataFetched = false;

    /**
     * Список запросов на релиз, которые в данный момент выложены. Массив вида [project1_id => $releaseRequest1, project2_id => $releaseRequest2, ...]
     */
    private $currentUsed = [];

    private $oldReleaseRequests = [];

    /**
     * @param bool $refresh
     *
     * @return array
     */
    public function getData($refresh = null)
    {
        $data = parent::getData($refresh ?? false);
        if ($refresh || !$this->additionalDataFetched) {
            $this->fetchAdditionalData($data);
            $this->additionalDataFetched = true;
        }

        return $data;
    }

    /**
     * @param array ReleaseRequest[]
     */
    private function fetchAdditionalData(array $data)
    {
        /** @var $data ReleaseRequest[] */
        $projectIds = [];
        $oldVersions = [];
        foreach ($data as $val) {
            $projectIds[] = $val->rr_project_obj_id;
            $oldVersions[] = $val->rr_old_version;
        }
        $projectIds = array_unique($projectIds);
        $oldVersions = array_filter(array_unique($oldVersions));

        $currentUsed = ReleaseRequest::findAllByAttributes([
            'rr_project_obj_id' => $projectIds,
            'rr_status' => ReleaseRequest::STATUS_USED,
        ]);
        foreach ($currentUsed as $releaseRequest) {
            $this->currentUsed[$releaseRequest->rr_project_obj_id] = $releaseRequest;
        }

        $list = ReleaseRequest::findAllByAttributes(array(
            'rr_build_version' => $oldVersions,
            'rr_project_obj_id' => $projectIds,
        ));

        foreach ($list as $releaseRequest) {
            /** @var $releaseRequest ReleaseRequest*/
            $this->oldReleaseRequests[$releaseRequest->rr_project_obj_id][$releaseRequest->rr_build_version] = $releaseRequest;
        }
    }

    /**
     * Возвращает текущий выложенный запрос релиза для проекта. Может вернуть null, если в этом проекте нет выложенной версии (например, новый проект)
     * @param int $projectId
     * @return ReleaseRequest|null
     */
    public function getCurrentUsedReleaseRequest($projectId)
    {
        return isset($this->currentUsed[$projectId]) ? $this->currentUsed[$projectId] : null;
    }

    /**
     * Возвращает сборку, на которую будет откатываться от текущей
     * @param int $projectId
     * @param string $buildVersion
     * @return ReleaseRequest|null
     */
    public function getOldReleaseRequest($projectId, $buildVersion)
    {
        return isset($this->oldReleaseRequests[$projectId][$buildVersion]) ? $this->oldReleaseRequests[$projectId][$buildVersion] : null;
    }
}
