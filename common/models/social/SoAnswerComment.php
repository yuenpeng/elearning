<?php

namespace common\models\social;

use Yii;
use common\base\BaseActiveRecord;
use yii\db\Expression;
use common\models\framework\FwUser;

/**
 * This is the model class for table "{{%so_answer_comment}}".
 *
 * @property string $kid
 * @property string $answer_id
 * @property string $user_id
 * @property string $comment_content
 * @property string $version
 * @property string $created_by
 * @property integer $created_at
 * @property string $created_from
 * @property string $updated_by
 * @property integer $updated_at
 * @property string $updated_from
 * @property string $is_deleted
 */
class SoAnswerComment extends BaseActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%so_answer_comment}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['answer_id', 'user_id', 'comment_content'], 'required'],
            [['comment_content'], 'string'],
            [['created_at', 'updated_at'], 'integer'],
            [['kid', 'answer_id', 'user_id', 'created_by', 'updated_by'], 'string', 'max' => 50],
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
            'answer_id' => Yii::t('frontend', 'answer_id'),
            'user_id' => Yii::t('frontend', 'user_id'),
            'comment_content' => Yii::t('frontend', 'comment_content'),
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

    public function SubAnswerComment()
    {
        if ($this->save()) {
            SoAnswer::addFieldNumber($this->answer_id,"comment_num");//SoAnswer::updateAll(['comment_num' => new Expression("comment_num+1")], "kid = '$this->answer_id'");
            return true;
        }
        return false;
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
