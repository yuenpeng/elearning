<?php

namespace common\models\boe;

use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use yii\db\Expression;
use Yii;

/**
 * 特训营学员积分信息表 Model 
 * @author Zhenglk
 * @email zhenglk@cg789.com
 * @property string $kid
 * @property string $orgnization_id
 * @property string $battalion_id
 * @property string $area_id
 * @property integer $rank_num
 * @property string $date
 * @property integer $version
 * @property string $created_by
 * @property integer $created_at
 * @property string $created_from
 * @property string $created_ip
 * @property string $updated_by
 * @property integer $updated_at
 * @property string $updated_from
 * @property string $updated_ip
 * @property string $is_deleted
 */
class BoeTxyClassRank extends \common\models\boe\BoeBaseActiveRecord {

    /**
     * @inheritdoc
     */
    public static function tableName() {
        return '{{%boe_txy_class_rank}}';
    }

    /**
     * @inheritdoc
     */
    public function rules() {
        return [
            [['rank_num', 'version', 'created_at', 'updated_at'], 'integer'],
            [['date'], 'safe'],
            [['kid', 'orgnization_id', 'battalion_id', 'area_id', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['is_deleted'], 'string', 'max' => 1],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return [
            'kid' => '班级排名KID',
            'orgnization_id' => '班级ID',
            'battalion_id' => '营级ID',
            'area_id' => '大区ID',
            'rank_num' => '排名序号',
            'date' => '记录日期',
            'version' => Yii::t('common', 'version'),
            'created_by' => Yii::t('common', 'created_by'),
            'created_at' => Yii::t('common', 'created_at'),
            'created_from' => Yii::t('common', 'created_from'),
            'updated_by' => Yii::t('common', 'updated_by'),
            'updated_at' => Yii::t('common', 'updated_at'),
            'updated_from' => Yii::t('common', 'updated_from'),
            'is_deleted' => Yii::t('common', 'is_deleted'),
        ];
    }

}
