<?php
/**
 * Created by PhpStorm.
 * User: Vendoryep
 * Date: 2017/12/8
 * Time: 11:16
 */

namespace common\models\learning;


use common\base\BaseActiveRecord;


class LnSetting extends BaseActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%ln_setting}}';
    }




}