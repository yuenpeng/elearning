<?php

namespace common\models\social;

use Yii;
use common\base\BaseActiveRecord;
use common\models\framework\FwUser;

/**
 * This is the model class for table "{{%so_answer}}".
 *
 * @property string $kid
 * @property string $question_id
 * @property string $user_id
 * @property string $answer_content
 * @property string $comment_num
 * @property string $share_num
 * @property string $collect_num
 * @property string $version
 * @property string $created_by
 * @property integer $created_at
 * @property string $created_from
 * @property string $updated_by
 * @property integer $updated_at
 * @property string $updated_from
 * @property string $is_deleted
 */
class SoAnswer extends BaseActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%so_answer}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['question_id', 'user_id', 'answer_content'], 'required'],
            [['answer_content'], 'string'],
            [['comment_num', 'share_num', 'collect_num'], 'number'],
            [['created_at', 'updated_at'], 'integer'],
            [['kid', 'question_id', 'user_id', 'created_by', 'updated_by'], 'string', 'max' => 50],
            [['created_from','updated_from'], 'string', 'max' => 50],

            [['version'], 'number'],
            [['version'], 'default', 'value'=> 1],

            [['is_deleted'], 'string', 'max' => 1],
            [['is_deleted'], 'in', 'range' => [self::DELETE_FLAG_NO, self::DELETE_FLAG_YES]],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'kid' => Yii::t('frontend', 'kid'),
            'question_id' => Yii::t('frontend', 'question_id'),
            'user_id' => Yii::t('frontend', 'user_id'),
            'answer_content' => Yii::t('frontend', 'answer_content'),
            'comment_num' => Yii::t('frontend', 'comment_num'),
            'share_num' => Yii::t('frontend', 'share_num'),
            'collect_num' => Yii::t('frontend', 'collect_num'),
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

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getFwUser()
    {
        return $this->hasOne(FwUser::className(), ['kid' => 'user_id'])
            ->onCondition([FwUser::realTableName() . '.is_deleted' => self::DELETE_FLAG_NO]);
    }
}
