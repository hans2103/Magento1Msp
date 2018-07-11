<?php

class Mage_Msp_Model_Gateway_Ideal extends Mage_Msp_Model_Gateway_Abstract
{
	protected $_code    		= "msp_ideal";
	protected $_model   		= "ideal";
	public $_gateway 			= "IDEAL";
	public $bank_id				= '';
	protected $_formBlockType 	= 'msp/idealIssuers';  

	public function assignData($data)
	{
		if ( !($data instanceof Varien_Object) ) {
			$data = new Varien_Object($data);
		}
			foreach($data as $key => $value)
			{
				if($key == '_data'){
					foreach($value as $index => $val)
					{
						if($index == 'bankid'){
							$bank_id = $val;
						}	
					}
				}
			}

			$_SESSION['bankid'] = $bank_id;
			
		return $this;
	}
	
	public function getOrderPlaceRedirectUrl() 
	{
		return $this->getModelUrl("msp/standard/redirect/issuer/" . $this->_issuer );
	}
	
	public function getPayment($storeId = null) 
	{
		$payment = parent::getPayment($storeId);
		$payment->setIssuer($this->_issuer);
		return $payment;
	}
	
	public function getIdealIssuers($storeId = null)
	{
		$idealissuers = parent::getIdealIssuersHTML($storeId);
		return $idealissuers;
	}
}