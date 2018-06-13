<?php

namespace common\models\framework;

use Yii;
use common\base\BaseActiveRecord;

/**
 * This is the model class for table "{{%fw_order}}".
 *
 * @property string $kid
 * @property string $company_id
 * @property string $user_id
 * @property string $order_number
 * @property string $purchase_number
 * @property string $order_type
 * @property string $order_status
 * @property string $pay_method
 * @property integer $apply_at
 * @property integer $submit_at
 * @property integer $reply_at
 * @property integer $open_at
 * @property integer $cancel_at
 * @property integer $pay_at
 * @property string $pay_number
 * @property string $operate_by
 * @property string $description
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
 *
 * @property FwOrderContent[] $fwOrderContents
 */
class FwOrder extends BaseActiveRecord {
	
	const PAY_METHOD_ONLINE="线上支付";
	const PAY_METHOD_OFFLINE="线下支付";
	
	public $submit_at_begin;
	public $submit_at_end;
	public $search_key;
	public $s_pay_method;
	public $s_order_type;
	public $s_order_status;
	public $company_name;
	public $c_content_result_time;
	public $c_content_result_input;
	public $c_content_result_textarea;
	
	public $treatment_result;
	/**
	 * @inheritdoc
	 */
	public static function tableName() {
		return '{{%fw_order}}';
	}
	
	/**
	 * @inheritdoc
	 */
	public function rules() {
		return [ 
				[ 
						[ 
								'kid',
								'company_id',
								'user_id',
								'order_number',
								'purchase_number',
								'created_by',
								'created_at' 
						],
						'required' 
				],
				[ 
						[ 
								'apply_at',
								'submit_at',
								'reply_at',
								'open_at',
								'cancel_at',
								
								'version',
								'created_at',
								'updated_at' 
						],
						'integer' 
				],
				[ 
						[ 
								'pay_number' ,'c_content_result_input'
						],
						'number' 
				],
				[ 
						[ 
								'description' 
						],
						'string' 
				],
				[ 
						[ 
								'kid',
								'company_id',
								'user_id',
								'order_number',
								'purchase_number',
								'operate_by',
								'created_by',
								'created_from',
								'created_ip',
								'updated_by',
								'updated_from',
								'updated_ip' 
						],
						'string',
						'max' => 50 
				],
				[ 
						[ 
								'order_type',
								'order_status',
								'pay_method',
								'is_deleted' 
						],
						'string',
						'max' => 1 
				] ,
				[
						['email'],'email'
				]
		];
	}
	
	/**
	 * @inheritdoc
	 */
	public function attributeLabels() {
		return [ 
				'kid' => Yii::t ( 'common', 'kid' ),
				'search_key' =>'',
				'submit_at_begin' => '',
				'submit_at_end' => '',
				's_pay_method'=>'',
				's_order_type'=>'',
				's_order_status'=>'',
				'contact_name' => Yii::t ( 'common', 'o_contacts' ),
				'mobile_no' => Yii::t ( 'common', 'mobile' ),
				'email' => Yii::t ( 'common', 'user_email' ),
				'treatment_result'=>Yii::t ( 'common', 'o_treatment_result' ),
				'company_name' => Yii::t ( 'common', 'o_company_name' ),
				'c_content_result_time'=>Yii::t ( 'common', 'time_validity' ),
				'c_content_result_input'=>Yii::t ( 'common', 'o_student_num' ),
				'c_content_result_textarea'=>Yii::t ( 'common', 'o_requirement_desc' ),
				'user_id' => Yii::t ( 'common', 'user_id' ),
				'order_number' => Yii::t ( 'common', 'o_order_number' ),
				'purchase_number' => Yii::t ( 'common', 'o_purchase_number' ),
				'order_type' => Yii::t ( 'common', 'o_order_type' ),
				'order_status' => Yii::t ( 'common', 'o_order_status' ),
				'pay_method' => Yii::t ( 'common', 'o_pay_method' ),
				'apply_at' => Yii::t ( 'common', 'o_apply_at' ),
				'submit_at' => Yii::t ( 'common', 'o_submit_at' ),
				'reply_at' => Yii::t ( 'common', 'o_reply_at' ),
				'open_at' => Yii::t ( 'common', 'o_open_at' ),
				'cancel_at' => Yii::t ( 'common', 'o_cancel_at' ),
				'pay_at' => Yii::t ( 'common', 'o_pay_at' ),
				'pay_number' => Yii::t ( 'common', 'o_pay_number' ),
				'operate_by' => Yii::t ( 'common', 'o_operate_by' ),
				'description' => Yii::t ( 'common', 'o_description' ),
				'version' => Yii::t ( 'common', 'version' ),
				'created_by' => Yii::t ( 'common', 'created_by' ),
				'created_at' => Yii::t ( 'common', 'created_at' ),
				'created_from' => Yii::t ( 'common', 'created_from' ),
				'created_ip' => Yii::t ( 'common', 'created_ip' ),
				'updated_by' => Yii::t ( 'common', 'updated_by' ),
				'updated_at' => Yii::t ( 'common', 'updated_at' ),
				'updated_from' => Yii::t ( 'common', 'updated_from' ),
				'updated_ip' => Yii::t ( 'common', 'updated_ip' ),
				'is_deleted' => Yii::t ( 'common', 'is_deleted' ) 
		];
	}
	public function getCompanyName() {
		$company = FwCompany::findOne ( $this->company_id );
		return $company->company_name;
	}
	
	public function getOrderType() {
		return $this->order_type;
	}
	
	public function getPayMethod() {
		return $this->pay_method;
	}
	
	public function getOrderStatus() {
		return $this->order_status;
    }

    
    public function getOperater(){
    	return $this->operate_by;
    }
    
    
    public function getPayMethodSelects(){
    	$result=['1'=>FwOrder::PAY_METHOD_ONLINE,
    			'2'=>FwOrder::PAY_METHOD_OFFLINE,
    		
    	];
    	return $result;
    }
    
    
     public function  getOrderTypeSelects(){
     	return $result;
     }
     
      public function  getOrderStatusSelects(){
      	return $result;
      }
    
    
}
