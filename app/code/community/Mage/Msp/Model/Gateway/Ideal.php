<?php

class Mage_Msp_Model_Gateway_Ideal extends Mage_Msp_Model_Gateway_Abstract
{
	protected $_code    		= "msp_ideal";
	protected $_model   		= "ideal";
	public $_gateway 			= "IDEAL";
	protected $_formBlockType 	= 'msp/idealIssuers';  
	
	public function getOrderPlaceRedirectUrl() 
	{	
		$bank = $_POST['payment']['msp_ideal_bank'];
		$url = $this->getModelUrl("msp/standard/redirect/issuer/".$this->_issuer);
		if (!strpos($url, "?")) $url .= '?bank=' . $bank;
		else $url .= '&bank=' . $bank;
		return $url;
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