<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2015/12/4
 * Time: 10:12
 */
namespace common\helpers\boe;

use common\models\learning\LnCourseEnroll;
use common\services\boe\BoeCourseEnrollService;
use Yii;
use yii\helpers\Html;

class BoeTExportHelper
{
    const NEWLINE = "\t\n";

    const TAB = "\t";

    /**
     * 导出Csv文件
     * @param string $header 表头 eg:No.,姓名,邮箱,手机,状态,开始时间,完成时间,成绩
     * @param array $data 数据
     * @param string $filename 导出文件名
     * @param string $split 分隔符
     */
    public static function exportCsv($header, $data, $filename = "output", $split = ",")
    {
        $header = Yii::t('common', 'serial_number') . $split . $header;
        $content = $header . self::NEWLINE;
        $i = 1;
        foreach ($data as $item) {
            $content .= $i . $split;
            for ($j = 0; $j < count($item); $j++) {
                if ($j + 1 === count($item)) {
                    $content .= self::TAB . $item[$j] . self::NEWLINE;
                } else {
                    $content .= self::TAB . $item[$j] . $split;
                }
            }
            $i++;
        }

        $conv = new TCharsetConv('utf-8', 'utf-8bom');
        $content = $conv->convert($content);
//        $content = $conv->convert(Html::encode($content));

        header('Content-Description: File Transfer');
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Type: application/force-download");
        header("Content-Type: application/download");
        header('Content-Type: application/octet-stream; charset=gb2312');
        header('Content-Disposition: attachment; filename=' . urlencode($filename . '.csv'));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Pragma: public');
        header('Content-Length: ' . strlen($content));
        ob_clean();
        flush();
        echo $content;
    }

    public static function courseEnrollUserByAdmin($data, $filename = 'course_enroll_user')
    {
        // Create new PHPExcel object
        $objPHPExcel = new \PHPExcel();

        // Set document properties
        $objPHPExcel->getProperties()->setCreator("E-Learning")
            ->setLastModifiedBy("E-Learning")
            ->setTitle(Yii::t('frontend', 'face_course_enroll_user_list'))
            ->setKeywords("E-Learning");

        // Add header
        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1', Yii::t('common', 'real_name'))
            ->setCellValue('B1', Yii::t('frontend', 'work_number'))
            ->setCellValue('C1', Yii::t('frontend', 'email'))
            ->setCellValue('D1', Yii::t('frontend', '性别'))
            ->setCellValue('E1', Yii::t('frontend', '手机'))
            ->setCellValue('F1', Yii::t('frontend', '职级'))
            ->setCellValue('G1', Yii::t('frontend', '发薪地'))
            ->setCellValue('H1', Yii::t('frontend', '入职时间'))
            ->setCellValue('I1', Yii::t('frontend', '体系'))
            ->setCellValue('J1', Yii::t('frontend', '组织'))
            ->setCellValue('K1', Yii::t('frontend', '组织全路径'))
            ->setCellValue('L1', Yii::t('frontend', 'position'))
            ->setCellValue('M1', Yii::t('frontend', 'manager_level'))
            ->setCellValue('N1', Yii::t('frontend', 'enroll_time'))
            ->setCellValue('O1', Yii::t('common', 'status'));

        $objSheet = $objPHPExcel->setActiveSheetIndex(0);
        foreach ($data as $index => $item) {
            $row = $index + 2;
            // Miscellaneous glyphs, UTF-8
            $objSheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_TEXT);
            //体系组织
            //$tx = explode('/', $item['orgnization_name_path']);
            $orgnization = (new \yii\db\Query())
                        ->select(['system', 'orgnization'])
                        ->from('eln_boe_user_orgnization')
                        ->where(['user_id' => $item['user_id']])
                        ->one();
            //性别
            if($item['gender']=='male'){
                $gender = '男';
            }elseif($item['gender']=='female'){
                $gender = '女';
            }elseif($item['gender']=='privacy'){
                $gender = '保密';
            }elseif($item['gender']=='other'){
                $gender = '其他';
            }else{
                $gender = $item['gender'];
            }
            //发薪地
            $payroll_place =  BoeCourseEnrollService::UserPayrollPlace($item['user_id']);

            $objSheet->setCellValue('A' . $row, $item['real_name'])
                ->setCellValue('B' . $row, $item['user_no'])
                ->setCellValue('C' . $row, $item['email'])
                ->setCellValue('D' . $row, $gender)
                ->setCellValue('E' . $row, $item['mobile_no'])
                ->setCellValue('F' . $row, $item['rank'])
                ->setCellValue('G' . $row, $payroll_place)
                ->setCellValue('H' . $row, $item['onboard_day'])
                ->setCellValue('I' . $row, $orgnization['system'])
                ->setCellValue('J' . $row, $orgnization['orgnization'])
                ->setCellValue('K' . $row, $item['orgnization_name_path'].'/'.$item['orgnization_name'])
                ->setCellValue('L' . $row, $item['position_name'])
                ->setCellValue('M' . $row, $item['position_mgr_level_txt'])
                ->setCellValue('N' . $row, date(Yii::t('common', 'date_format_ymd'), $item['enroll_time']));

            $status = '';
            if ($item['approved_state'] == LnCourseEnroll::APPROVED_STATE_APPLING) {
                $status = Yii::t('frontend', 'pending_approval_leadership');
            } else if ($item['approved_state'] == LnCourseEnroll::APPROVED_STATE_APPROVED) {
                if ($item['enroll_type'] == LnCourseEnroll::ENROLL_TYPE_REG) {
                    $status = Yii::t('frontend', 'pending_approval_admin');
                } elseif ($item['enroll_type'] == LnCourseEnroll::ENROLL_TYPE_ALLOW) {
                    $status = Yii::t('frontend', 'enroll_success');
                } elseif ($item['enroll_type'] == LnCourseEnroll::ENROLL_TYPE_ALTERNATE) {
                    $status = Yii::t('frontend', 'alternate');
                } elseif ($item['enroll_type'] == LnCourseEnroll::ENROLL_TYPE_DISALLOW) {
                    $status = Yii::t('frontend', 'admin_rejected');
                }
            } else if ($item['approved_state'] == LnCourseEnroll::APPROVED_STATE_REJECTED) {
                $status = Yii::t('frontend', 'manager_rejected');
            } else if ($item['approved_state'] == LnCourseEnroll::APPROVED_STATE_CANCELED) {
                $status = Yii::t('frontend', 'invalid');
            }

            $objSheet->setCellValue('O' . $row, $status);
        }

        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(12);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(25);
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(8);
        $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(18);
        $objPHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('K')->setWidth(100);
        $objPHPExcel->getActiveSheet()->getColumnDimension('L')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('M')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('N')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('O')->setWidth(20);

        // $objPHPExcel->getActiveSheet()->getStyle('A6')->getFont()->setName('宋体')->setSize(30);

        // Rename worksheet
        $objSheet->setTitle('User List');


        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $objPHPExcel->setActiveSheetIndex(0);


        // Redirect output to a client’s web browser (Excel2007)
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        // If you're serving to IE 9, then the following may be needed
        header('Cache-Control: max-age=1');

        // If you're serving to IE over SSL, then the following may be needed
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
        header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header('Pragma: public'); // HTTP/1.0

        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');

    }

    public static function courseEnrollUserByTeacher($data, $filename = 'course_enroll_user')
    {
        // Create new PHPExcel object
        $objPHPExcel = new \PHPExcel();

        // Set document properties
        $objPHPExcel->getProperties()->setCreator("E-Learning")
            ->setLastModifiedBy("E-Learning")
            ->setTitle(Yii::t('frontend', 'face_course_enroll_user_list'))
            ->setKeywords("E-Learning");

        // Add header
        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1', Yii::t('common', 'real_name'))
            ->setCellValue('B1', Yii::t('frontend', 'department'))
            ->setCellValue('C1', Yii::t('frontend', 'position'))
            ->setCellValue('D1', Yii::t('frontend', 'email'))
            ->setCellValue('E1', Yii::t('common', 'mobile'));

        $objSheet = $objPHPExcel->setActiveSheetIndex(0);
        foreach ($data as $index => $item) {
            $row = $index + 2;
            // Miscellaneous glyphs, UTF-8
            $objSheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_TEXT);

            $objSheet->setCellValue('A' . $row, $item['real_name'])
                ->setCellValue('B' . $row, $item['orgnization_name_path'] . '/' . $item['orgnization_name'])
                ->setCellValue('C' . $row, $item['position_name'])
                ->setCellValue('D' . $row, $item['email'])
                ->setCellValue('E' . $row, $item['mobile_no']);
        }

        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(22);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(15);

//        $objPHPExcel->getActiveSheet()->getStyle('A6')->getFont()->setName('宋体')->setSize(30);

// Rename worksheet
        $objSheet->setTitle('User List');


// Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $objPHPExcel->setActiveSheetIndex(0);


// Redirect output to a client’s web browser (Excel2007)
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
// If you're serving to IE 9, then the following may be needed
        header('Cache-Control: max-age=1');

// If you're serving to IE over SSL, then the following may be needed
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
        header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header('Pragma: public'); // HTTP/1.0

        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');

    }
}