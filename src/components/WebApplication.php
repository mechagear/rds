<?php
namespace whotrades\rds\components;

class WebApplication extends \yii\web\Application
{
    /**
     * @return string
     */
    public function getTimezone()
    {
        $user = $this->getUser();
        if ($user->getIsGuest()) {
            return parent::getTimeZone();
        }

        return $this->getUser()->identity->profile->timezone;
    }

    /**
     * Метод используется в частности при авторизации для формирования имени сессии
     * Так как по дефолту для определения идентификатора приложения используется абсолютный путь - то при каждом релизе происходило вылогинивание
     *
     * @since WTA-45
     * @return string
     */
    public function getId()
    {
        return 'service-rds';
    }

    public function setRuntimePath($path)
    {
        // an: Создаем папку для временных файлов, если её ещё нету
        if (!is_dir($path)) {
            mkdir($path, 0777);
        }

        return parent::setRuntimePath($path);
    }

    /**
     * Хак для поддержки старой базы ссылок
     * @param string $route
     * @return \yii\base\Controller
     */
    public function createControllerByID($route)
    {
        $newStyleRoute = preg_replace_callback('~([a-z0-9])([A-Z])~', function ($matches) {
            return $matches[1] . "-" . strtolower($matches[2]);
        }, $route);

        return parent::createControllerByID($newStyleRoute);
    }
}
