<?php
class Mage_Msp_Model_Service_Quote extends Mage_Sales_Model_Service_Quote
{
    public function submitOrder()
    {
        $order 		= parent::submitOrder();
		if (Mage::getStoreConfig('payment/msp/keep_cart') || Mage::getStoreConfig('msp/settings/keep_cart')) {
			$this->_quote->setIsActive(true);
		}
        return $order;
    }
}
?>