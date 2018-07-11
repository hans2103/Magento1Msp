<?php

class MultiSafepay_Msp_Model_Servicecost_Invoice_Totals extends Mage_Sales_Model_Order_Invoice_Total_Subtotal {

	public function collect(Mage_Sales_Model_Order_Invoice $invoice) {
		$order = $invoice->getOrder();
		$invoice->setServicecost($order->getServicecost());
		$invoice->setBaseServicecost($order->getBaseServicecost());	
		$invoice->setServicecostTax($order->getServicecostTax());
		$invoice->setBaseServicecostTax($order->getBaseServicecostTax());	

		$invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() +$invoice->getServicecost()- $invoice->getServicecostTax());
		$invoice->setGrandTotal($invoice->getGrandTotal() + $invoice->getServicecost()- $invoice->getServicecostTax());	
		$invoice->setSubtotalInclTax($invoice->getSubtotalInclTax() - $invoice->getServicecostTax());
		$invoice->setBaseSubtotalInclTax($invoice->getBaseSubtotalInclTax() - $invoice->getServicecostTax());
		$invoice->setServicecostPdf($order->getServicecostPdf());

	
		return $this;
	}
}