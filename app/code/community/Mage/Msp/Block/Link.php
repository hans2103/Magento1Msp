<?php
class Mage_Msp_Block_Link extends Mage_Core_Block_Template
{
    protected function _construct()
    {

    }
    
    public function _toHtml()
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        
        if (Mage::getModel('msp/checkout')->isAvailable($quote) && $quote->validateMinimumAmount()) {
            return parent::_toHtml();
        }
        return '';
    }
}
?>