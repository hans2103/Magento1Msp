<?php

class Mage_Msp_Model_Gateway_Standard extends Mage_Msp_Model_Gateway_Abstract
{
	protected $_module = "payment";
	protected $_code = "msp";
	protected $_formBlockType = 'msp/gateways';
	protected $_loadSettingsConfig = false; // dont use default settings

	public function setParams($params)
	{
		if (isset($params['gateway']))
		{
			$this->_gateway = preg_replace("|[^a-zA-Z]+|", "", $params['gateway']);
		}
	}

	

	public function getNotificationUrl()
	{
		return $this->getModelUrl("msp/mspPayment/notification");
	}

	public function getOrderPlaceRedirectUrl() 
	{
		return $this->getModelUrl("msp/standard/redirect/model/standard/gateway/" . $this->_gateway);
	}
}
