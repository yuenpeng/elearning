<?php
namespace common\models\boe;
use common\base\BaseActiveRecord;
use common\helpers\TLoggerHelper;
use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use common\models\framework\FwUser;
use Yii;


class BoeNewGrowUser extends BoeBaseActiveRecord {
	

    public static function tableName(){
		return 'eln_boe_new_grow_user';
	}

	 /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return [
            'kid' => 'kid',
			'user_id'=>'user_id',
            'year' => Yii::t('common', '年份'),
            'user_no' => Yii::t('common', 'user_no'),
            'user_name' => Yii::t('common', 'real_name'),
			'master_name' => Yii::t('common', '师傅姓名'),
			'master_no' => Yii::t('common', '师傅工号'),
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
	 * @inheritdoc
	 */
	public function rules() {
		return [
            [['user_no','user_name','master_name','master_no','user_id'], 'required'],
            [['user_no','user_name','master_name','master_no'], 'string'],
            [[ 'version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['is_deleted'], 'string', 'max' => 1],
            [['user_no'],'unique'],
			[['year'], 'default', 'value'=> 0],
        ];

	}

    /**
     * 根据user_no获取用户
     * @param $user_no
     * @return array|bool|\yii\db\ActiveRecord[]
     */

	public function getMembers($user_no){
		$user_no = trim($user_no);
		if(!empty($user_no)){
			$members = $this->find(false)->where(['user_no'=>$user_no])->asArray()->all();
			return $members;
		}
		return false;
	}

	/**
	 * excel到入数据
	 */
	//导入数据
	public function saveImport($data, $file, $fileMd5)
	{
		$errColum = 'J';
		if (!file_exists($file)) {
			return false;
		}

		$reader = \PHPExcel_IOFactory::createReaderForFile($file);

		$objPHPExcel = $reader->load($file);

		$sheet = $objPHPExcel->setActiveSheetIndex(0);
		$sheet->getColumnDimension($errColum)->setAutoSize(true);
		$sheet->setCellValue($errColum.'1', 'result');

		$dataFrom = 'GrowUserImport_' . $fileMd5;
		$user_no =[];
		//操作数组
		$saveList = [];
		$changeList = [];
		$deleteList = [];
		TLoggerHelper::Error("Import User");
		foreach ($data as $index => $item) {
			if ($item['op'] === 'A') {
				//工号唯一检查
				$model = new BoeNewGrowUser();
				$query = $model->find(false)
					->andFilterWhere(['=', 'user_no', $item['user_no']]);
				$count = $query->count(1);
				if ($count|| in_array($item['user_no'], $user_no)) {
					$sheet->setCellValue($errColum . ($index + 1), '工号 already exists');
					continue;
				}
				if ($item['user_no']=='') {
					$sheet->setCellValue($errColum . ($index + 1), '工号 is incorrect');
					continue;
				}
				if ($item['user_name']=='') {
					$sheet->setCellValue($errColum . ($index + 1), '姓名  is incorrect');
					continue;
				}
				if ($item['master_no']=='') {
					$sheet->setCellValue($errColum . ($index + 1), '师傅工号  is incorrect');
					continue;
				}
				if ($item['master_name']=='') {
					$sheet->setCellValue($errColum . ($index + 1), '师傅姓名  is incorrect');
					continue;
				}
				$user_no[] = $item['user_no'];

				//用户信息检查
				$where  = array('and',
					array('=', 'is_deleted', '0'),
					array('=', 'user_no',$item['user_no']),
				);
				//检查学员信息
				$user = FwUser::find(false)->select('kid,real_name')->where($where)->asArray()->one();
				if(empty($user)){
					$sheet->setCellValue($errColum . ($index + 1), '用户不存在');
					continue;
				}elseif($user['real_name']!=$item['user_name']){
					$sheet->setCellValue($errColum . ($index + 1), '用户姓名错误');
					continue;
				}
				//检查师傅信息
				$master_where  = array('and',
					array('=', 'is_deleted', '0'),
					array('=', 'user_no',$item['master_no']),
				);
				$master = FwUser::find(false)->select('real_name')->where($master_where)->asArray()->one();
				if(empty($master)){
					$sheet->setCellValue($errColum . ($index + 1), '师傅信息不存在');
					continue;
				}elseif($master['real_name']!=$item['master_name']){
					$sheet->setCellValue($errColum . ($index + 1), '师傅姓名错误');
					continue;
				}


				//组织者检查
				$model->year = $item['year'];
				$model->user_id = $user['kid'];
				$model->user_no = $item['user_no'];
				$model->user_name = $item['user_name'];
                $model->master_no = $item['master_no'];
                $model->master_name = $item['master_name'];
				$sheet->setCellValue($errColum . ($index + 1), 'success');
				$saveList[] = $model;
			} elseif ($item['op'] === 'U') {
				$model = BoeGrowUser::findOne(['user_no' => $item['user_no']]);
				if ($model) {
					$model->year = $item['year'];
                    $model->user_no = $item['user_no'];
                    $model->user_name = $item['user_name'];
                    $model->master_no = $item['master_no'];
                    $model->master_name = $item['master_name'];
					$sheet->setCellValue('I' . ($index + 1), 'success');
					$changeList[] = $model;
				} else {
					$sheet->setCellValue('I' . ($index + 1), 'user not found');
				}
			} elseif ($item['op'] === 'D') {
				$model = BoeGrowUser::findOne(['user_no' => $item['user_no']]);
				if ($model) {
					$sheet->setCellValue('I' . ($index + 1), 'success');
					$deleteList[] = $model;
				} else {
					$sheet->setCellValue('I' . ($index + 1), 'user not found');
				}
			}
		}

		//生成导入结果集
		$class = get_class($reader);
		$class = explode('_', $class);
		$writerType = end($class);

		$objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, $writerType);
		$objWriter->save($file);

		$errMsg = '';

		//数据入库处理开始
		if (count($saveList) > 0) {
			$ret = BaseActiveRecord::batchInsertSqlArray($saveList, $errMsg);
			if (!$ret) {
				return $errMsg;
			}

		}
		if (count($changeList) > 0) {
			$ret = BaseActiveRecord::batchUpdateNormalMode($changeList, $errMsg);
			if (!$ret) {
				return $errMsg;
			}
		}
		if (count($deleteList) > 0) {
			$ret = BaseActiveRecord::batchDeleteNormalMode($deleteList, $errMsg);
			if (!$ret) {
				return $errMsg;
			}
		}
		return true;
	}

    /**
     *  根据KID获取用户信息
     * @param $user_id
     * @return array|bool|null|\yii\db\ActiveRecord
     */

     public function  getMemberInfo($user_id){
         $info = $this->find(false)->where(['kid'=>$user_id])->asArray()->one();
         if(!empty($info)){
             return $info;
         }
         return false;
     }

	public function getInfo($id = 0, $key = '*', $create_mode = 0, $debug = 0) {
		return $this->CommonGetInfo($id, $key, $create_mode, $debug);
	}

	public function saveInfo($data, $debug = 0) {
		return $this->CommonSaveInfo($data, $debug);
	}

	public function deleteInfo($id = 0) {
		return $this->CommonDeleteInfo($id);
	}
}
?>