<?php

/**
 *
 * @category MultiSafepay
 * @package  MultiSafepay_Msp
 */
class MultiSafepay_Msp_Model_Config_Sources_Order_Currency {

    /**
     * @return array
     */
    public function toOptionArray() {
		$currencies = array();
		$codes = Mage::app()->getStore()->getAvailableCurrencyCodes(true);
		foreach($codes as $code){
			$currencies[] = array(
                "value" => $code,
                "label" => Mage::helper("msp")->__($code),
            );
		}
		return $currencies;
    }

}
