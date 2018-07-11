<?php

class MultiSafepay_Msp_Block_Afterpay extends Mage_Payment_Block_Form
{

    public $_code;
    public $_issuer;
    public $_model;
    public $_countryArr = null;
    public $_country;
    public $_quote;

    protected function _construct()
    {
        $this->setTemplate('msp/afterpay.phtml');
        $this->_quote = Mage::getSingleton('checkout/session')->getQuote();

        parent::_construct();
    }

    public function getGender()
    {
        $genderTable = array('1' => 'male', '2' => 'female');
        $gender = $this->_quote->getCustomerGender();
        return isset($genderTable[$gender]) ? $genderTable[$gender] : null;
    }


    public function getBirthday()
    {
        $birthday = $this->_quote->getCustomerDob();
        $birthday_formatted = Mage::app()->getLocale()->date($birthday, null, null, false)->toString('dd-MM-yyyy');

        return ($birthday == NULL) ? NULL : $birthday_formatted;
    }

    public function getPhonenumber()
    {
      $phonenumber = $this->_quote->getBillingAddress()->getTelephone();

      return ($phonenumber ?: NULL);
    }
}
