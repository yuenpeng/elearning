<?php

namespace common\models\report;

use Yii;
use common\base\BaseActiveRecord;

/**
 * This is the model class for table "{{%rp_st_course_day_data}}".
 *
 * @property string $kid
 * @property integer $YEAR
 * @property integer $MONTH
 * @property string $TIME
 * @property string $domain_id
 * @property string $course_id
 * @property double $reg_user_num
 * @property double $comp_user_num
 * @property double $comp_rate
 * @property double $score
 * @property string $version
 * @property string $created_by
 * @property integer $created_at
 * @property string $created_from
 * @property string $updated_by
 * @property integer $updated_at
 * @property string $updated_from
 * @property string $is_deleted
 */
class RpStCourseDayData extends BaseActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%rp_st_course_day_data}}';
    }

   
}
