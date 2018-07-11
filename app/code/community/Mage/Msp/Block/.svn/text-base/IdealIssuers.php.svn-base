<?php

class Mage_Msp_Block_IdealIssuers extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        $gateway_select = Mage::getStoreConfig("msp/msp_ideal/bank_select");
        if ($gateway_select) 
		{
			parent::_construct();
			$this->setTemplate('msp/idealissuers.phtml');
        }
    }
 
    public function getPaymentOptions()
    {
        $msp = Mage::getSingleton("msp/gateway_ideal");
        $base = $msp->getBase();
        $options = $base->getGateways();
        
        if (!isset($options['IDEAL']['issuers']))
        {
			return array();
        }
        return $options['IDEAL']['issuers'];
    }
}