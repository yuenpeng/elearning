<?php

namespace common\models\boe;

use Yii;

/**
 * This is the model class for table "{{%boe_mixture_project_category}}".
 *
 * @property string $kid
 * @property string $tree_node_id
 * @property string $parent_category_id
 * @property string $company_id
 * @property string $category_code
 * @property string $category_name
 * @property string $description
 * @property string $status
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
class BoeMixtureProjectCategory extends \common\base\BaseActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%boe_mixture_project_category}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['tree_node_id', 'company_id', 'category_code', 'category_name'], 'required'],
            [['description'], 'string'],
            [['version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'tree_node_id', 'parent_category_id', 'company_id', 'category_code', 'category_name', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['status', 'is_deleted'], 'string', 'max' => 1],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'kid' => Yii::t('boe', 'Kid'),
            'tree_node_id' => Yii::t('boe', 'Tree Node ID'),
            'parent_category_id' => Yii::t('boe', 'Parent Category ID'),
            'company_id' => Yii::t('boe', 'Company ID'),
            'category_code' => Yii::t('boe', 'Category Code'),
            'category_name' => Yii::t('boe', 'Category Name'),
            'description' => Yii::t('boe', 'Description'),
            'status' => Yii::t('boe', 'Status'),
            'version' => Yii::t('boe', 'Version'),
            'created_by' => Yii::t('boe', 'Created By'),
            'created_at' => Yii::t('boe', 'Created At'),
            'created_from' => Yii::t('boe', 'Created From'),
            'created_ip' => Yii::t('boe', 'Created Ip'),
            'updated_by' => Yii::t('boe', 'Updated By'),
            'updated_at' => Yii::t('boe', 'Updated At'),
            'updated_from' => Yii::t('boe', 'Updated From'),
            'updated_ip' => Yii::t('boe', 'Updated Ip'),
            'is_deleted' => Yii::t('boe', 'Is Deleted'),
        ];
    }
}
