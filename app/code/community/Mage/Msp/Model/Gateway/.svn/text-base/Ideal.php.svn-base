<?php

class Mage_Msp_Model_Gateway_Ideal extends Mage_Msp_Model_Gateway_Abstract
{
	protected $_code    = "msp_ideal";
	protected $_model   = "ideal";
	protected $_gateway = "IDEAL";
	
	protected $_formBlockType = 'msp/idealIssuers';  

	public function setParams($params)
	{
		if (isset($params['issuer']))
		{
			$this->_issuer = preg_replace("|[^a-zA-Z]+|", "", $params['issuer']);
		}
	}

	public function assignData($data)
	{
		if (!($data instanceof Varien_Object)) 
		{
			$data = new Varien_Object($data);
		}

		$this->_issuer = preg_replace("|[^a-zA-Z]+|", "", $data->getMspIdealissuer());
		return $this;
	}
	
	public function getOrderPlaceRedirectUrl() 
	{
		return $this->getModelUrl("msp/standard/redirect/issuer/" . $this->_issuer);
	}
	
	public function getPayment($storeId = null) 
	{
		$payment = parent::getPayment($storeId);
		$payment->setIssuer($this->_issuer);
		return $payment;
	}
}