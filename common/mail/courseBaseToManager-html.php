<?php
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $user common\models\framework\FwUser */

Yii::$app->urlManager->suffix = ".html";
$resetLink = Yii::$app->urlManager->createAbsoluteUrl(['student/index']);
$resetLink = str_replace("/api/","/",$resetLink);
$resetLink = str_replace("/app/","/",$resetLink);
$resetLink = str_replace("/backend/","/",$resetLink);
?>
<table border="1" cellpadding="0" width="98%" style="border:solid #2990CA 1.5pt;margin:0 auto">
    <tr>
        <td style="border:none;padding:18pt 18pt 18pt 18pt">
            <table border="0" cellpadding="0" width="100%" style="width:100.0%">
                <tr style="height:80pt">
                    <td valign="top" style="padding:.75pt .75pt .75pt .75pt;">
                        <p align="right" style="text-align:right">
                            <span style="font-size:20.0pt;"><?= Yii::t('system','frontend_name')?></span>
                        </p>
                        <p>
                            <span style="font-size:18.0pt;">报名通知<br></span>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td valign="top" style="padding:.75pt .75pt .75pt .75pt">
                        <p>
                            <span style="font-size:10.0pt;">您好：<?= Html::encode($manager->real_name) ?> (<?= Html::encode($manager->user_name) ?>)，</span>
                        </p>
                        <p>
                            <span style="font-size:10.0pt;"><?=$user->real_name?>(<?=$user->user_name?>)已申请报名《<a href="<?= Yii::$app->urlManager->createAbsoluteUrl(['resource/course/view', 'id' => $course->kid, 'from' => 'mail']) ?>"><?=$course->course_name?></a>》课程，并确认报名信息。请登录京东方大学平台审核学员资质，谢谢！</span>
                        </p>
                    </td>
                </tr>
            </table>
            <div align="center" style="text-align:center">
                <span><hr size="1" width="100%" noshade style="color:black" align="center"></span>
            </div>
            <p style='margin-bottom:12.0pt'>
                <span style='font-size:10.0pt;'>请注意：此邮件由平台自动发出，无需回复</span>
            </p>
        </td>
    </tr>
</table>