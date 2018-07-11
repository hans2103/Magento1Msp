<?php

/**
 *
 * @category MultiSafepay
 * @package  MultiSafepay_Msp
 */
class MultiSafepay_Msp_Model_Gateway_Afterpay extends MultiSafepay_Msp_Model_Gateway_Abstract
{
    public $_model = "afterpay";
    public $_gateway = "AFTERPAY";

    protected $_code = "msp_afterpay";
    protected $_formBlockType = 'msp/afterpay';
    protected $_canUseCheckout = true;

    public function getOrderPlaceRedirectUrl()
    {
        if (isset($_POST['payment']['birthday'])) {
            $birthday = $_POST['payment']['birthday'];
        } else {
            $birthday = '';
        }

        if (isset($_POST['payment']['salutation'])) {
            $salutation = $_POST['payment']['salutation'];
        } else {
            $salutation = '';
        }

        if (isset($_POST['payment']['phonenumber'])) {
            $phonenumber = $_POST['payment']['phonenumber'];
        } else {
            $phonenumber = '';
        }

        $url = $this->getModelUrl("msp/standard/redirect/issuer/" . $this->_issuer);
        if (!strpos($url, "?"))
            $url .= '?birthday=' . $birthday . '&salutation=' . $salutation. '&phonenumber=' . $phonenumber;
        else
            $url .= '&birthday=' . $birthday . '&salutation=' . $salutation. '&phonenumber=' . $phonenumber;
        return $url;
    }

}
