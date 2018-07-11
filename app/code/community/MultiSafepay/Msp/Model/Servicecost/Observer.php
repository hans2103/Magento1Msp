<?php

class MultiSafepay_Msp_Model_Servicecost_Observer {

    public function invoiceSaveAfter(Varien_Event_Observer $observer) {
        $invoice = $observer->getEvent()->getInvoice();
        if ($invoice->getServicecost()) {
            $order = $invoice->getOrder();
            $order->setServicecostInvoiced($invoice->getServicecost());
            $order->setBaseServicecostInvoiced($invoice->getBaseServicecost());
            $order->setServicecostTaxInvoiced($invoice->getServicecostTax());
            $order->setBaseServicecostTaxInvoiced($invoice->getBaseServicecostTax());
        }
        return $this;
    }

    public function creditmemoSaveAfter(Varien_Event_Observer $observer) {

        $data = Mage::app()->getRequest()->getPost('creditmemo');

        $refunded_servicecost = $data['servicecost'];

        $creditmemo = $observer->getEvent()->getCreditmemo();
		$order = $creditmemo->getOrder();
		
        if ($refunded_servicecost != '0') {
           
            $order->setServicecostRefunded($refunded_servicecost);
            $order->setBaseServicecostRefunded($refunded_servicecost);

            if ($order->getTotalOfflineRefunded() != null) {
                $order->setTotalOfflineRefunded($order->getTotalOfflineRefunded() - $creditmemo->getServicecost() + $refunded_servicecost);
                $order->setBaseTotalOfflineRefunded($order->getBaseTotalOfflineRefunded() - $creditmemo->getServicecost() + $refunded_servicecost);
            } else {
                $order->setTotalOnlineRefunded($order->getTotalOnlineRefunded() - $creditmemo->getServicecost() + $refunded_servicecost);
                $order->setBaseTotalOnlineRefunded($order->getBaseTotalOnlineRefunded() - $creditmemo->getServicecost() + $refunded_servicecost);
            }
            $order->setBaseTotalRefunded($order->getBaseTotalRefunded() - $creditmemo->getServicecost() + $refunded_servicecost);
            $order->setTotalRefunded($order->getTotalRefunded() - $creditmemo->getServicecost() + $refunded_servicecost);

            //$order->setServicecostTaxRefunded($creditmemo->getServicecostTax());
            //$order->setBaseServicecostTaxRefunded($creditmemo->getBaseServicecostTax());
        }else{
	         $order->setBaseTotalRefunded($order->getBaseTotalRefunded() - $creditmemo->getServicecost() + $refunded_servicecost);
            $order->setTotalRefunded($order->getTotalRefunded() - $creditmemo->getServicecost() + $refunded_servicecost);
        }
        //$creditmemo->setGrandTotal(5);
        /* if ($creditmemo->getServicecost()) {
          $order = $creditmemo->getOrder();
          $order->setServicecostRefunded($creditmemo->getServicecost());
          $order->setBaseServicecostRefunded($creditmemo->getBaseServicecost());
          $order->setServicecostTaxRefunded($creditmemo->getServicecostTax());
          $order->setBaseServicecostTaxRefunded($creditmemo->getBaseServicecostTax());
          } */
        return $this;
    }

    public function creditmemoRefund(Varien_Event_Observer $observer) {
        $creditmemo = $observer->getEvent()->getCreditmemo();
        $data = Mage::app()->getRequest()->getPost('creditmemo');
        $order = $creditmemo->getOrder();
 
		$base_credit = $order->getBaseTotalRefunded() + $data['servicecost'] + $data['shipping_amount'] + $data['adjustment_positive'] - $data['adjustment_negative'] - $order->getBaseServicecost();
        $credit = $order->getTotalRefunded() + $data['servicecost'] + $data['shipping_amount'] + $data['adjustment_positive'] - $data['adjustment_negative'] - $order->getBaseServicecost();
	
        $creditmemo->setGrandTotal($credit);
        $creditmemo->setBaseGrandTotal($base_credit);
    }

}
