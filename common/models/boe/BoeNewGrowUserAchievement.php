<?php
namespace common\models\boe;
use common\helpers\TLoggerHelper;
use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use common\models\framework\FwUser;
use Yii;
use common\base\BaseActiveRecord;


class BoeNewGrowUserAchievement extends BoeBaseActiveRecord {
	

    public static function tableName(){
		return 'eln_boe_new_grow_user_achievement';
	}

	 /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return [
            'kid' => 'kid',
            'user_id'=>'user_id',
            'user_no' => Yii::t('common', 'user_no'),
            'id_number' => Yii::t('common', 'id_number'),
            'real_name' => Yii::t('common', 'real_name'),
            'step1_score' => Yii::t('common', '职场准备学分'),
            'step2_score' => Yii::t('common', '特训营学分'),
            'step4_other' => Yii::t('common', '其他'),
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
            [['user_no','real_name','step1_score','step2_score','user_id'], 'required'],
            [['user_no','id_number','real_name'], 'string'],
            [[ 'version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['is_deleted'], 'string', 'max' => 1],
            [['user_no'],'unique'],
            [['step4_other','id_number'], 'default', 'value'=> ''],
 
        ];

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
				$model = new BoeNewGrowUserAchievement();
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
				if ($item['real_name']=='') {
					$sheet->setCellValue($errColum . ($index + 1), '姓名  is incorrect');
					continue;
				}
				if ($item['step1_score']=='') {
					$sheet->setCellValue($errColum . ($index + 1), '职场准备学分  is incorrect');
					continue;
				}
				if ($item['step2_score']=='') {
					$sheet->setCellValue($errColum . ($index + 1), '特训营学分  is incorrect');
					continue;
				}
				$user_no[] = $item['user_no'];

				//用户信息检查
				$where  = array('and',
					array('=', 'is_deleted', '0'),
					array('=', 'user_no',$item['user_no']),
				);
				$user = FwUser::find(false)->select('kid,orgnization_id,id_number,user_name')->where($where)->asArray()->one();
				if(empty($user)){
					$sheet->setCellValue($errColum . ($index + 1), '用户不存在');
					continue;
				} elseif($user['real_name']!=$item['user_name']){
					$sheet->setCellValue($errColum . ($index + 1), '用户姓名错误');
					continue;
				}


				//组织者检查
				$model->user_no = $item['user_no'];
                $model->user_id = $user['kid'];
				$model->id_number = $item['id_number'];
				$model->real_name = $item['real_name'];
				$model->step1_score = $item['step1_score'];
				$model->step2_score = $item['step2_score'];
				$sheet->setCellValue($errColum . ($index + 1), 'success');
				$saveList[] = $model;
			} elseif ($item['op'] === 'U') {
				$model = BoeNewGrowUserAchievement::findOne(['user_no' => $item['user_no']]);
				if ($model) {
					$model->id_number = $item['id_number'];
					$model->real_name = $item['real_name'];
					$model->step1_score = $item['step1_score'];
					$model->step2_score = $item['step2_score'];
					$sheet->setCellValue('I' . ($index + 1), 'success');
					$changeList[] = $model;
				} else {
					$sheet->setCellValue('I' . ($index + 1), 'user not found');
				}
			} elseif ($item['op'] === 'D') {
				$model = BoeNewGrowUserAchievement::findOne(['user_no' => $item['user_no']]);
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
}

?>