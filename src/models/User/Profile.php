<?php
namespace whotrades\rds\models\User;

class Profile extends \dektrium\user\models\Profile
{
    /**
     * @return string
     */
    public static function tableName()
    {
        return 'rds.profile';
    }
}
