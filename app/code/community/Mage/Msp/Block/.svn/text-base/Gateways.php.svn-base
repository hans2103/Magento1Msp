<?php

class Mage_Msp_Block_Gateways extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        $gateway_select = Mage::getStoreConfig("payment/msp/gateway_select");
        if ($gateway_select) 
		{
			parent::_construct();
			$this->setTemplate('msp/gateways.phtml');
        }
    }
 
    public function getPaymentOptions()
    {
        $msp = Mage::getSingleton("msp/gateway_standard");
        $base = $msp->getBase();
        //return $base->getGateways();
    }
}