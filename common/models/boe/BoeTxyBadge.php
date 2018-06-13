<?php

namespace common\models\boe;
use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use yii\db\Expression;
use Yii;
/**
 * 特训营徽章墙配置信息表 Model
 * @author Zhenglk
 * @email zhenglk@cg789.com
 * @property string $kid
 * @property string $code
 * @property string $img_url
 * @property string $type
 * @property integer $recommend_sort1
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
class BoeTxyBadge extends BoeBaseActiveRecord {
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%boe_txy_badge}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['code'], 'required'],
            [['recommend_sort1', 'version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'code', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['img_url'], 'string', 'max' => 250],
            [['type', 'is_deleted'], 'string', 'max' => 1],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'kid' => 'Kid',
            'code' => 'Code',
            'img_url' => 'Img Url',
            'type' => 'Type',
            'recommend_sort1' => 'Recommend Sort1',
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
