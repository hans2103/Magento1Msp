<?php
require_once(Mage::getBaseDir('lib').DS.'multisafepay'.DS.'MultiSafepay.combined.php');
abstract class Mage_Msp_Model_Gateway_Abstract extends Mage_Payment_Model_Method_Abstract
{
	protected $_module = "msp";   // config root (msp or payment)
	protected $_settings = "msp"; // config root for settings (always msp for now)
	protected $_code;             // payment method code
	protected $_model;            // payment model
	public $_gateway;          // msp 'gateway'
	protected $_idealissuer;	  // ideal issuer
	protected $_params;
	protected $_loadSettingsConfig = true; // load 'settings' before payment config
	protected $_loadGatewayConfig  = true; 
	protected $_canCapture = true;
	protected $_canCapturePartial = true;
	protected $_canUseForMultishipping = false;

	public $payment;

	
	// For 1.3.2.4
	public function isAvailable($quote=null)
	{
		return $this->getConfigData('active');
	}
  
	public function setSortOrder($order)
	{
		// Magento tries to set the order from payment/, instead of our msp/
		$this->sort_order = $this->getConfigData('sort_order');
	}
	
	
	/**
	* Append the current model to the URL
	*/
	function getModelUrl($url) 
	{
		if (!empty($this->_model))
		{
			$url .= "/model/" . $this->_model;
		}
		return Mage::getUrl($url, array("_secure" => true));
	}

	/**
	* Magento will use this for payment redirection
	*/
	public function getOrderPlaceRedirectUrl() 
	{
		return $this->getModelUrl("msp/standard/redirect");
	}
	
	/**
	* Get the payment object and set the right config values
	*/
	public function getPayment($storeId = null) 
	{
		$payment = Mage::getSingleton("msp/payment");
		
		// get store id
		if (!$storeId)
		{
			$storeId = $this->getStore();
		}
		
		// basic settings
		$configSettings = array();
		if ($this->_loadSettingsConfig)
		{
			$configSettings = Mage::getStoreConfig($this->_settings . "/settings", $storeId);
		}
    
		// load gateway specific config and merge
		$configGateway = array();
		if ($this->_loadGatewayConfig)
		{
			$configGateway = Mage::getStoreConfig($this->_module . "/" . $this->_code, $storeId);
		}
    
		// merge
		$config = array_merge($configSettings, $configGateway);

		// payment
		$payment->setConfigObject($config);
		$payment->setNotificationUrl($this->getNotificationUrl());
		$payment->setReturnUrl($this->getReturnUrl());
		$payment->setCancelUrl($this->getCancelUrl());
		$payment->setGateway($this->getGateway());
		$payment->setIdealIssuer($this->getIdealIssuer());
		return $payment;
	}
	
	/**
	* Start fco xml transaction transaction
	*/
	function startPayAfterTransaction()
	{
		// pass store (from this getLastOrderId) to the getPayment?
		$payment = $this->getPayment();
		return $payment->startPayAfterTransaction();
	}
	
	
	/**
	* Start a transaction
	*/
	function startTransaction()
	{
		// pass store (from this getLastOrderId) to the getPayment?
		$payment = $this->getPayment();
		return $payment->startTransaction();
	}
	
	/**
	* Notification
	*/
	function notification($id)
	{
		
		// pass store (from this id) to the getPayment
		$order = Mage::getSingleton('sales/order')->loadByIncrementId($id);
		$storeId = $order->getStore();
		$payment = $this->getPayment($storeId);
		return $payment->notification($id);
	}
	
	public function getIdealIssuersHTML(){
		$storeId = $this->getStore();
		$configSettings = array();
		if ($this->_loadSettingsConfig)
		{
			$configSettings = Mage::getStoreConfig($this->_settings . "/settings", $storeId);
		}
		
		//$idealselect = 'test';
		$msp 							= 	new MultiSafepay();
		
		if($configSettings['test_api'] == 'test'){
			$msp->test                    	= true;
		}else{
			$msp->test                    	= false;
		}
		
		$msp->merchant['account_id']  	= $configSettings['account_id'];
		$msp->merchant['site_id']     	= $configSettings['site_id'];
		$msp->merchant['site_code']   	= $configSettings['secure_code'];
		
		$iDealIssuers = $msp->getIdealIssuers();

		if($configSettings['test_api'] == 'test'){
			return $iDealIssuers['issuers'];
		}else{
			return $iDealIssuers['issuers']['issuer'];
		}
	}
	
	/**
	* Notification URL of the model
	*/
	public function getNotificationUrl()
	{
		return $this->getModelUrl("msp/standard/notification");
	}
  
	/**
	* Return URL of the model
	*/
	public function getReturnUrl()
	{
		return Mage::getUrl("msp/standard/return", array("_secure" => true));
	}

	/**
	* Cancel URL of the model
	*/
	public function getCancelUrl()
	{
		return Mage::getUrl("msp/standard/cancel", array("_secure" => true));
	}
  
	/**
	* Selected 'gateway'
	*/
	public function getGateway()
	{
		return $this->_gateway;
	}
	
	public function getIdealIssuer(){
		return $this->_idealissuer;
	}
  
	/**
	* Pass params to the model
	*/
	public function setParams($params)
	{
		$this->_params = $params;
	}
	
	/**
	* Get config data
	*/
	public function getConfigData($field, $storeId = null)
	{
		if (null === $storeId) 
		{
			$storeId = $this->getStore();
		}
		$path = $this->_module . "/" . $this->_code . '/' . $field;
		return Mage::getStoreConfig($path, $storeId);
	}
}