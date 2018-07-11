<?php

/**
 *
 * @category MultiSafepay
 * @package  MultiSafepay_Msp
 */
class MultiSafepay_Msp_Model_Service_Quote extends Mage_Sales_Model_Service_Quote {

    /**
     * @return Mage_Sales_Model_Order
     */
    public function submitOrder() {
        $order = parent::submitOrder();

        if (Mage::app()->getStore()->isAdmin()) {
            return $order;
        }

        if (Mage::getStoreConfig('payment/msp/keep_cart', $this->_quote->getStoreId()) ||
                Mage::getStoreConfig('msp/settings/keep_cart', $this->_quote->getStoreId()) ||
                $this->_quote->getPayment()->getMethod() == 'msp_payafter') {

            $this->_quote->setIsActive(true)->save();
            $this->_quote->setReservedOrderId(null)->save();
        }

        return $order;
    }

}
