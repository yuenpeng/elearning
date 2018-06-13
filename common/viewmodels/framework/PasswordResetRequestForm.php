<?php
namespace common\viewmodels\framework;

use common\models\framework\FwUser;
use Yii;
use yii\base\Model;

/**
 * Password reset request form
 */
class PasswordResetRequestForm extends Model
{
    public $email;

    public $user_no;

    public $error_message;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['email', 'filter', 'filter' => 'trim'],
            ['email', 'required'],
            ['user_no','string','max'=>20],
            ['email', 'email'],
            ['email', 'exist',
                'targetClass' => '\common\models\framework\FwUser',
                'filter' => [
                    'status' => FwUser::STATUS_FLAG_NORMAL,
                    'is_deleted' => FwUser::DELETE_FLAG_NO
                ],
                'message' => \Yii::t('common','no_such_email')
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'email' => Yii::t('common', 'email'),
            'user_no' =>Yii::t('common', 'user_no'),
        ];
    }

    /**
     * Sends an email with a link, for resetting the password.
     *
     * @return boolean whether the email was send
     */
    public function sendEmail($email_is_repeat = false)
    {
        /* @var $user FwUser */
        if($email_is_repeat){
            $user = FwUser::findOne([
                'status' => FwUser::STATUS_FLAG_NORMAL,
                'email' => $this->email,
                'user_no'=>$this->user_no,
            ]);
        }else{
            $user = FwUser::findOne([
                'status' => FwUser::STATUS_FLAG_NORMAL,
                'email' => $this->email,
            ]);
        }

        if ($user && !empty($user->email)) {
            if ($user->password_reset_token == null) {
                $user->generatePasswordResetToken();
            }
            else if (!FwUser::isPasswordResetTokenValid($user->password_reset_token)) {
                $user->generatePasswordResetToken();
            }

            $user->find_pwd_req_at = time();
            if ($user->save()) {

                $emailSwitch = false;
                if (isset(Yii::$app->params['email_switch'])){
                    $emailSwitch = Yii::$app->params['email_switch'];
                }

                if ($emailSwitch) {
                    return Yii::$app->mailer->compose(['html' => 'passwordResetToken-html', 'text' => 'passwordResetToken-text'], ['user' => $user])
                        ->setFrom([Yii::$app->params['supportEmail'] => Yii::t('system', 'system_robot')])
                        ->setTo($this->email)
                        ->setSubject(Yii::t('common', 'request_password_subject'))
                        ->send();
                }
                else {
                    return false;
                }
            }
        }

        return false;
    }
}
