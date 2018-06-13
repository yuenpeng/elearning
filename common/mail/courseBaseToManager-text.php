<?php

/* @var $this yii\web\View */
/* @var $user common\models\framework\FwUser */

Yii::$app->urlManager->suffix = ".html";
$resetLink = Yii::$app->urlManager->createAbsoluteUrl(['student/index']);
$resetLink = str_replace("/api/","/",$resetLink);
$resetLink = str_replace("/app/","/",$resetLink);
$resetLink = str_replace("/backend/","/",$resetLink);
?>
您好：<?= $manager->real_name ?> (<?= $manager->user_name ?>)，

<?= $user->real_name ?> (<?= $user->user_name ?>)已申请报名《<?=$course->course_name?>》课程,并确认报名信息。请登录京东方大学平台审核学员资质，谢谢！

请注意：此邮件是平台自动发出的，无需回复