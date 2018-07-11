<?php

class Mage_Msp_Model_Gateway_Fastcheckout extends Mage_Msp_Model_Gateway_Abstract
{
	protected $_code    = "msp_fastcheckout";
	protected $_model   = "fastcheckout";
	public $_gateway = "FASTCHECKOUT";
	protected $_formBlockType = 'msp/gateways';  
}
