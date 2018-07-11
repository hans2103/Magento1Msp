<?php

class MultiSafepay_Msp_Block_Bno extends Mage_Payment_Block_Form
{

    public $_code;
    public $_issuer;
    public $_model;
    public $_countryArr = null;
    public $_country;
    public $_quote;

    protected function _construct()
    {
        $this->setTemplate('msp/bno.phtml');
        $this->_quote = Mage::getSingleton('checkout/session')->getQuote();

        parent::_construct();
    }

    public function getBirthday()
    {
        $birthday = $this->_quote->getCustomerDob();
        $birthday_formatted = Mage::app()->getLocale()->date($birthday, null, null, false)->toString('dd-MM-yyyy');

        return ($birthday == NULL) ? NULL : $birthday_formatted;
    }

}
