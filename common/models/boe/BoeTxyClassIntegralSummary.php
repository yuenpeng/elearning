<?php

namespace common\models\boe;

use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use yii\db\Expression;
use Yii;

/**
 * 特训营班级积分汇总信息表 Model
 * @author Zhenglk
 * @email zhenglk@cg789.com
 * @property string $kid
 * @property string $orgnization_id
 * @property string $battalion_id
 * @property string $area_id
 * @property date $summary_date
 * @property integer $summary_score 
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
class BoeTxyClassIntegralSummary extends BoeBaseActiveRecord {

    /**
     * @inheritdoc
     */
    public static function tableName() {
        return '{{%boe_txy_class_integral_summary}}';
    }

    /**
     * @inheritdoc
     */
    public function rules() {
        return [
            [['orgnization_id'], 'required'],
            [['summary_score'], 'number'],
            [['summary_date', 'version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'orgnization_id', 'battalion_id', 'area_id', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['is_deleted'], 'string', 'max' => 1],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return [
            'kid' => '班级积分信息KID',
            'orgnization_id' => '班级组织KID',
            'battalion_id' => '营队ID',
            'area_id' => '大区ID',
            'summary_date' => '统计时间',
            'summary_score' => '积分分数',
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
