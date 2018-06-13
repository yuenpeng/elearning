<?php

namespace common\models\boe;

use common\helpers\TFileModelHelper;
use common\helpers\TStringHelper;
use common\models\framework\FwDomain;
use common\models\framework\FwUser;
use common\services\framework\DictionaryService;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "{{%boe_mixture_project}}".
 *
 * @property string $kid
 * @property string $company_id
 * @property string $short_code
 * @property string $category_id
 * @property string $program_code
 * @property string $program_name
 * @property string $program_desc
 * @property string $program_desc_nohtml
 * @property string $program_language
 * @property string $currency
 * @property string $theme_url
 * @property string $program_price
 * @property integer $default_credit
 * @property string $is_display_pc
 * @property string $is_display_mobile
 * @property string $status
 * @property string $start_time
 *  * @property string $end_time
 * @property integer $register_number
 * @property integer $enroll_number
 * @property string $approval_rule
 * @property integer $enroll_start_time
 * @property integer $enroll_end_time
 * @property integer $open_start_time
 * @property integer $open_end_time
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
class BoeMixtureProject extends \common\base\BaseActiveRecord
{
    const DISPLAY_PC_YES = "1";
    const DISPLAY_MOBILE_YES = "1";
    const DISPLAY_PC_NO = "0";
    const DISPLAY_MOBILE_NO = "0";
    const IS_PUB_YES = "1";
    const IS_PUB_NO = "0";
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%boe_mixture_project}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['company_id', 'short_code', 'program_name','status'], 'required'],
            [['program_desc', 'program_desc_nohtml'], 'string'],
            [['program_price'], 'number'],
            [['default_credit', 'register_number', 'enroll_number','start_time','end_time','enroll_start_time', 'enroll_end_time', 'open_start_time', 'open_end_time', 'version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'company_id', 'short_code', 'category_id', 'program_code', 'program_language', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['program_name'], 'string', 'max' => 100],
            [['currency', 'approval_rule'], 'string', 'max' => 20],
            [['theme_url'], 'string', 'max' => 500],
            [['is_display_pc', 'is_display_mobile', 'status','is_deleted'], 'string', 'max' => 1],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'kid' => Yii::t('boe', 'Kid'),
            'company_id' => Yii::t('boe', 'Company ID'),
            'short_code' => Yii::t('boe', 'Short Code'),
            'category_id' => Yii::t('boe', 'Category ID'),
            'program_code' => Yii::t('boe', 'Program Code'),
            'program_name' => Yii::t('boe', '项目名称'),
            'program_desc' => Yii::t('boe', 'Program Desc'),
            'program_desc_nohtml' => Yii::t('boe', 'Program Desc Nohtml'),
            'program_language' => Yii::t('boe', 'Program Language'),
            'currency' => Yii::t('boe', 'Currency'),
            'theme_url' => Yii::t('boe', 'Theme Url'),
            'program_price' => Yii::t('boe', 'Program Price'),
            'default_credit' => Yii::t('boe', 'Default Credit'),
            'is_display_pc' => Yii::t('boe', 'Is Display Pc'),
            'is_display_mobile' => Yii::t('boe', 'Is Display Mobile'),
            'status' => Yii::t('boe', 'Status'),
            'start_time' => Yii::t('boe', 'start_time'),
            'end_time' => Yii::t('boe', 'end_time'),
            'register_number' => Yii::t('boe', 'Register Number'),
            'enroll_number' => Yii::t('boe', 'Enroll Number'),
            'approval_rule' => Yii::t('boe', 'Approval Rule'),
            'enroll_start_time' => Yii::t('boe', 'Enroll Start Time'),
            'enroll_end_time' => Yii::t('boe', 'Enroll End Time'),
            'open_start_time' => Yii::t('boe', 'Open Start Time'),
            'open_end_time' => Yii::t('boe', 'Open End Time'),
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

    /**
     * @param $domainList
     * @return string
     */
    public function getDomainNameByText($domainList="",$length = ""){
        $str = array();
        if (empty($domainList)){
            $resourceDomain =BoeMixtureProjectDomain::find(false)->andFilterWhere(['project_id'=>$this->kid])->andFilterWhere(['status'=>BoeMixtureProjectDomain::STATUS_FLAG_NORMAL])->distinct()->select('domain_id')->asArray()->all();
            if (!empty($resourceDomain)){
                $resourceDomain = ArrayHelper::map($resourceDomain, 'domain_id', 'domain_id');
                $domainList = array_keys($resourceDomain);
            }
        }
        if (!is_array($domainList)) $domainList = explode(',', $domainList);
        foreach($domainList as $val){
            $domain = FwDomain::findOne($val);
            if (!empty($domain) && $domain->status == FwDomain::STATUS_FLAG_NORMAL) {
                $str[] = $domain->domain_name;
            }
        }
        $str = join('、',$str);
        if (!empty($length)){
            $str =TStringHelper::subStr($str, $length, 'utf-8', 0, '...');
        }
        return $str;
    }

    /**
     * 获取创建人信息
     */

    public function getCreatedNameById(){
        $model = FwUser::findOne($this->created_by);
        return $model->real_name;
    }


    public function getProjectCover(){
//        $file = LnFiles::findOne(['kid',$this->theme_url],false);
        $tFileModel = new TFileModelHelper();
        return $tFileModel->secureLink($this->theme_url);
    }

    /**
     * 根据字典分类与值获取字典详细信息
     * @return string
     */
    public function getDictionaryText($cate_code, $val)
    {
        if (empty($cate_code)) {
            return "";
        } else {
            $dictionaryService = new DictionaryService();
            $name = $dictionaryService->getDictionaryNameByValue($cate_code, $val);

            return $name;
        }
    }

    public function getCourseCategoryText(){
        $category = BoeMixtureProjectCategory::findOne($this->category_id);
        if($category){
            return $category->category_name;
        }
        else
        {
            return "";
        }
    }



    /**
     *   返回关联任务的个数
     * @return $this
     */

    public function getTaskCount(){
        $count = BoeMixtureProjectTask::find(false)->andFilterWhere(['project_id'=>$this->kid])->count();
        return $count;
    }

    public function getBoeMixtureProjectDomain(){
        return $this->hasMany(BoeMixtureProjectDomain::className(),['project_id'=>'kid'])
            ->onCondition([BoeMixtureProjectDomain::realTableName().'.is_deleted'=>self::DELETE_FLAG_NO]);
    }

}
