<?php

namespace common\services\txy2018;

use common\services\interfaces\service\RightInterface;
use common\services\txy2018\TxyService;
use common\base\BoeBase;
use common\models\framework\FwUser;
use common\models\txy2018\Txy2018StudentPatient;
use common\models\txy2018\Txy2018StudentLeave;
use common\models\boe\BoeBadword;
use common\services\boe\BoeBaseService;
use common\helpers\TNetworkHelper;
use yii\db\Query;
use Yii;

/**
 * 特训营学员相关
 * @author xinpeng
 */
class TxyStudentService {

    static $loadedObject = array();
    static $_env = array();
    private static $cacheTime = 0;
    private static $timeInterval = 43200;
    //private static $timeInterval = 0;
    private static $userInfoCacheTime = 600;
    private static $cacheNameFix = 'txy2018_student_';

    /**
     * isNoCacheMode当前是否处于重建缓存的状态
     * @return type
     */
    private static function isNoCacheMode() {
        return Yii::$app->request->get('no_cache') == 1 ? true : false;
    }

    /**
     * isNoCacheMode当前是否处于重建缓存的状态
     * @return type
     */
    private static function isDebugMode() {
        return Yii::$app->request->get('debug_mode') == 1 ? true : false;
    }

    /**
     * 读取缓存的封装
     * @param type $cache_name
     * @param type $debug
     * @return type
     */
    private static function getCache($cache_name) {
        if (self::isNoCacheMode()) {
            return NULL;
        }
        $new_cache_name = self::$cacheNameFix . (!is_scalar($cache_name) ? md5(serialize($cache_name)) : $cache_name);
        $sult = Yii::$app->cache->get($new_cache_name);
        $debug = self::isDebugMode();
        if ($debug) {
            echo "<pre>\nRead Info From Cache,Cache Name={$new_cache_name}\n";
            if ($sult) {
                print_r($sult);
            } else {
                print_r("Cache Not Hit");
            }
            echo "\n</pre>";
        }
        return $sult;
    }

    /**
     * 修改缓存的封装
     * @param type $cache_name
     * @param type $data
     * @param type $time
     * @param type $debug
     */
    private static function setCache($cache_name, $data = NULL, $time = 0) {
        $new_cache_name = self::$cacheNameFix . (!is_scalar($cache_name) ? md5(serialize($cache_name)) : $cache_name);
        $time = $time ? $time : self::$cacheTime;
        Yii::$app->cache->set($new_cache_name, $data, $time); // 设置缓存 
        $debug = self::isDebugMode();
        if ($debug) {
            echo "<pre>\nRead Info From DataBase,Cache Name={$new_cache_name}\n";
            print_r($data);
            echo "\n</pre>";
        }
    }

    
    /*
     * 获取2018特训营入营学员
     */
    public static function getStudentList($params = array()) {
        $sult = $where = array();
        $pageSize = $params['pageSize']?$params['pageSize']:10;
        $pageNo = $params['pageNo']?$params['pageNo']:1;
        $offset = ($params['pageNo'] - 1) * $pageSize;
        $where = array(
            'and', array('=', FwUser::tableName().'.is_deleted', 0),array('<>', 'status', FwUser::STATUS_FLAG_STOP)
        );
        //域
        if (isset($params['domain_id']) && $params['domain_id']) {
            $where[] = array(is_array($params['domain_id']) ? 'in' : '=', 'domain_id', $params['domain_id']);
        }
        //组织
        if (isset($params['orgnization_id']) && $params['orgnization_id']) {
            $where[] = array(is_array($params['orgnization_id']) ? 'in' : '=', FwUser::tableName().'.orgnization_id', $params['orgnization_id']);
        }
        //姓名
        if (isset($params['real_name']) && $params['real_name']) {
            $where[] = array('like', FwUser::tableName().'.real_name', $params['real_name']);
        }
        //账号
        if (isset($params['user_name']) && $params['user_name']) {
            $where[] = array(is_array($params['user_name']) ? 'in' : '=', FwUser::tableName().'.user_name', $params['user_name']);
        }
        //身份证
        if (isset($params['id_number']) && $params['id_number']) {
            $where[] = array('like', FwUser::tableName().'.id_number', $params['id_number']);
        }
        $fields = 'eln_fw_user.kid,eln_fw_user.real_name,eln_fw_user.user_name ,eln_fw_user.orgnization_id,eln_txy2018_student_leave.leave_type,eln_txy2018_student_leave.leave_reason';
        $query  =  FwUser::find(false)
        ->join('LEFT JOIN', 'eln_txy2018_student_leave', 'eln_txy2018_student_leave.user_id = '.FwUser::tableName().'.kid')
        ->where($where);
        //总数
        $total = $query->count();
        if($params['export']){//导出数据
            //结果集
            $list = $query->select($fields)
            ->orderBy(FwUser::tableName().'.created_at')
            ->asArray()->all();
        }else{//结果集
            $list = $query->select($fields)
            ->offset($offset)
            ->orderBy(FwUser::tableName().'.created_at')
            ->limit($pageSize)
            ->asArray()->all();
        }
        if(!empty($list)){
            foreach ($list as $key => $value) {
                $orgnization_path =BoeBaseService::getOrgnizationPath($value['orgnization_id']);
                $orgnization_name = explode('\\', $orgnization_path);
                //区营连
                $value['qu'] = $orgnization_name[1];   $value['yin'] = $orgnization_name[2];  $value['lian'] = $orgnization_name[3] ;
                //组织信息调用接口实时
                $params = $params = array(
                    'idNumber' => $value['user_name'], //身份证号 - 必填
                );
                $response = TNetworkHelper::HttpGet(Yii::t('api', 'java_view_url'), $params);
                $data = json_decode($response['content'],true);
                $data = $data['data'];
                if(!empty($data['id']))
                {
                   $value['businessGroup'] = $data['businessGroup'];   //体系
                   $value['organization'] = $data['organization'];  //组织
                   $value['center'] = $data['center'];  //中心
                   $value['bm'] = ''; //部门
                }
                $list[$key] = $value;
            }
        }
        //echo  $query->createCommand()->getRawSql();
        $sult = array('total'=>$total,'list'=>$list);
        return $sult; 
    }

    /*
     * 获取2018特训营病号连学员
     */
    public static function getStudentPatientList($params = array()) {
        $sult = $where = array();
        $pageSize = $params['pageSize']?$params['pageSize']:10;
        $pageNo = $params['pageNo']?$params['pageNo']:1;
        $offset = ($params['pageNo'] - 1) * $pageSize;
        $where = array(
            'and', array('=','eln_txy2018_student_patient.is_deleted', 0) 
        );
        //是否入营
        if(isset($params['is_in'])&&$params['is_in']){
            $where[] = array('=','is_in',$params['is_in']);
        }
        //时间
        if (isset($params['time']) && $params['time']) {
            $where[] = array('>', 'eln_txy2018_student_patient.in_at', strtotime($params['time']));
        }
        //组织
        if (isset($params['orgnization_id']) && $params['orgnization_id']) {
            $where[] = array(is_array($params['orgnization_id']) ? 'in' : '=', 'eln_txy2018_student_patient.orgnization_id', $params['orgnization_id']);
        }
 
        $fields = 'eln_fw_user.real_name,eln_txy2018_student_patient.*';
        $query  = Txy2018StudentPatient::find(false);
        //总数
        $total = $query->where($where)->count();
        //结果集
        $list = $query->select($fields)
        ->join('LEFT JOIN', 'eln_fw_user', 'eln_txy2018_student_patient.user_id = eln_fw_user.kid')
        ->offset($offset)
        ->orderBy('eln_txy2018_student_patient.in_at desc')
        ->limit($pageSize)
        ->asArray()->all();
        if(!empty($list)){
            foreach ($list as $key => $value) {
                $orgnization_path =BoeBaseService::getOrgnizationPath($value['orgnization_id']);
                $orgnization_name = explode('\\', $orgnization_path);
                //区
                $value['qu'] = $orgnization_name[1];
                //营连
                $value['yin'] = $orgnization_name[2];
                //连
                $value['lian'] = $orgnization_name[3];
                $list[$key] = $value;
            }
        }
        //echo  $query->createCommand()->getRawSql();
        $sult = array('total'=>$total,'list'=>$list);
        return $sult; 
    }
    /*
     * 获取2018特训营病号连统计数据
     * 时间和疾病种类二维统计
     * return array('date'=>array('7月18日'，'7月19日'，'7月20日'，'7月21日'，'7月22日')，
     * 'list'=>array())
     * 执行方案原理：1.分类型数据库统计 2.取全部数据后遍历统计
     */
    public static function getStudentPatientCount($params = array()) {
        $sult = $where = array();
        $where = array(
            'and', array('=','is_deleted', 0),array('=','is_in',1)
        );
        //时间
        if (isset($params['time']) && $params['time']) {
            $where[] = array('>', 'in_at', strtotime($params['time']));
        }
        //组织
        if (isset($params['orgnization_id']) && $params['orgnization_id']) {
            $where[] = array(is_array($params['orgnization_id']) ? 'in' : '=', 'orgnization_id', $params['orgnization_id']);
        }
        $fields = 'patient_type,FROM_UNIXTIME(in_at) as datetime,in_at,count(*) as total';
        $query  = Txy2018StudentPatient::find(false)->select($fields);
        $data = $query->where($where)->groupBy('in_at,patient_type')->asArray()->orderBy('in_at,patient_type')->all();
        $sult = array();
        $txy_patient_type = Yii::t('txy','txy_patient_type');

        //第一遍洗数据
        foreach ($data as  $value) {
            $patient_type = (string)$value['patient_type'];
            $in_at =   (date('m月d日',$value['in_at']));
            $sult['time'][$in_at] = $value['in_at'];
            $sult['list'][$patient_type][$in_at]['total'] += $value['total'];
        }
      
        //适应echarts数据结构
        //二维数组填数据
        foreach ($txy_patient_type as $k1 => $value1) {
            $tmp_array = array();$i=0;
            foreach ($sult['time'] as $k2 => $value2) {
                $i++;
                if(!($sult['list'][$k1][$k2])){
                    $sult['list'][$k1][$k2]['total'] = 0;
                } 
                $tmp_array[$i] = $sult['list'][$k1][$k2]['total'] + $tmp_array[$i-1];
            }
            $sult['list2'][$k1] = $tmp_array;
        }
        return $sult;
    }
    /*
     * 获取2018特训离职离营学员
     */
    public static function getStudentLeaveList($params = array()) {
        $sult = $where = array();
        $pageSize = $params['pageSize']?$params['pageSize']:10;
        $pageNo = $params['pageNo']?$params['pageNo']:1;
        $offset = ($params['pageNo'] - 1) * $pageSize;
        $where = array(
            'and', array('=', Txy2018StudentLeave::tableName().'.is_deleted', 0)
        );
      
        //组织
        if (isset($params['orgnization_id']) && $params['orgnization_id']) {
            $where[] = array(is_array($params['orgnization_id']) ? 'in' : '=', Txy2018StudentLeave::tableName().'.orgnization_id', $params['orgnization_id']);
        }
        //姓名
        if (isset($params['real_name']) && $params['real_name']) {
            $where[] = array('like', 'real_name', $params['real_name']);
        }
        //身份证
        if (isset($params['id_number']) && $params['id_number']) {
            $where[] = array('like',  Txy2018StudentLeave::tableName().'.id_number',$params['id_number']);
        }
        //离开类型
        if (isset($params['leave_type']) && $params['leave_type']) {
            $where[] = array('=', 'leave_type', $params['leave_type']);
        }
        $fields = 'eln_fw_user.kid as user_id,eln_fw_user.real_name,eln_fw_user.user_name ,eln_fw_user.orgnization_id,eln_txy2018_student_leave.leave_type,eln_txy2018_student_leave.leave_reason,eln_fw_user.created_at as join_time,eln_txy2018_student_leave.created_at,eln_txy2018_student_leave.updated_at';
        $query  =  Txy2018StudentLeave::find(false)
        ->join('LEFT JOIN', 'eln_fw_user', 'eln_txy2018_student_leave.user_id = '.FwUser::tableName().'.kid')
        ->where($where);
        //总数
        $total = $query->count();
        //结果集
        $list = $query->select($fields)
        ->offset($offset)
        ->orderBy(FwUser::tableName().'.created_at')
        ->limit($pageSize)
        ->asArray()->all();
        if(!empty($list)){
            foreach ($list as $key => $value) {
                //区营连信息
                $orgnization_path =BoeBaseService::getOrgnizationPath($value['orgnization_id']);
                $orgnization_name = explode('\\', $orgnization_path);
                //区
                $value['qu'] = $orgnization_name[1];
                //营连
                $value['yin'] = $orgnization_name[2];
                //连
                $value['lian'] = $orgnization_name[3];

                //组织信息调用接口实时
                $params = $params = array(
                    'idNumber' => $value['user_name'], //身份证号 - 必填
                );
                $response = TNetworkHelper::HttpGet(Yii::t('api', 'java_view_url'), $params);
                $data = json_decode($response['content'],true);
                $data = $data['data'];
                if(!empty($data['id']))
                {
                    $value['businessGroup'] = $data['businessGroup'];   //体系
                    $value['organization'] = $data['organization'];  //组织
                    $value['center'] = $data['center'];  //中心
                    $value['bm'] = ''; //部门
                }
                $list[$key] = $value;
            }
        }
        //echo  $query->createCommand()->getRawSql();
        $sult = array('total'=>$total,'list'=>$list);
        return $sult; 
    }
    /*
     * 管理员的学员搜索身
     * 
     * auth：songsang
     */
    public static function getManagerStudent($orgnization_id,$keys){
        //管辖区学员
        if($orgnization_id){
            $area_manage_student = TxyService::getManagerStudent($orgnization_id);
            $result = array();
            foreach ($area_manage_student['list'] as   $value) {
                if($value['user_name']&&strpos($value['user_name'],$keys)!== false){
                    $result[] = $value;
                }
            }
            return $result;
        }
    }
    public static function exportStudentLeave($data, $filename = 'eln_txy2018_student_leave'){
        // Create new PHPExcel object
        $objPHPExcel = new \PHPExcel();

        // Set document properties
        $objPHPExcel->getProperties()->setCreator("E-Learning")
            ->setLastModifiedBy("E-Learning")
            ->setTitle(Yii::t('frontend', 'eln_txy2018_student_leave'))
            ->setKeywords("E-Learning");
        // Add header
        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1', '序号')
            ->setCellValue('B1', '姓名')
            ->setCellValue('C1', '身份证')
            ->setCellValue('D1', '区')
            ->setCellValue('E1', '营')
            ->setCellValue('F1', '连')
            ->setCellValue('G1', '体系')
            ->setCellValue('H1', '组织')
            ->setCellValue('I1', '中心')
            ->setCellValue('J1', '是否离营')
            ->setCellValue('K1', '是否离职')
            ->setCellValue('L1', '离营原因')
            ->setCellValue('M1', '离职原因');

        $objSheet = $objPHPExcel->setActiveSheetIndex(0);
        foreach ($data as $index => $item) {
            $row = $index + 2;
            // Miscellaneous glyphs, UTF-8
            $objSheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_TEXT);
            
            $objSheet->setCellValue('A' . $row, ($index+1))
                ->setCellValue('B' . $row, $item['real_name'])
                ->setCellValue('C' . $row, "'".$item['user_name'])
                ->setCellValue('D' . $row, $item['qu'])
                ->setCellValue('E' . $row, $item['yin'])
                ->setCellValue('F' . $row, $item['lian'])
                ->setCellValue('G' . $row, $item['businessGroup'])
                ->setCellValue('H' . $row, $item['organization'])
                ->setCellValue('I' . $row, $item['center'])
                ->setCellValue('J' . $row, $item['leave_type']==1?'是':'否')
                ->setCellValue('K' . $row, $item['leave_type']==2?'是':'否')
                ->setCellValue('L' . $row, $item['leave_type']==1?$item['leave_reason']:'-')
                ->setCellValue('M' . $row, $item['leave_type']==2?$item['leave_reason']:'-');
        }

        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(12);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(25);
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('K')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('L')->setWidth(40);
        $objPHPExcel->getActiveSheet()->getColumnDimension('M')->setWidth(40);
      
        // Rename worksheet
        $objSheet->setTitle('2018特训营离职离营学员');

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
    /*
     * 改变用户状态
     * auth：songsang
     */
    public function UserStatusChange($user_id,$status='disabled'){
        if($user_id){
            $model =  FwUser::findOne($user_id); 
            if($model->kid){
                $model->status = $status=='disabled'?'2':'1';   
                $sult = $model->save();
            }  
        }    
    }
}
