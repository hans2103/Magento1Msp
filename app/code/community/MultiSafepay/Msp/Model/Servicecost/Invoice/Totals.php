<?php

class MultiSafepay_Msp_Model_Servicecost_Invoice_Totals extends Mage_Sales_Model_Order_Invoice_Total_Subtotal {

    public function collect(Mage_Sales_Model_Order_Invoice $invoice) {
        $order = $invoice->getOrder();
        $invoice->setServicecost($order->getServicecost());
        $invoice->setBaseServicecost($order->getBaseServicecost());
        $invoice->setServicecostTax($order->getServicecostTax());

        $invoice->setBaseServicecostTax($order->getBaseServicecostTax());

        $invoice->setBaseTaxAmount  ($order->getBaseTaxAmount()+$order->getBaseServicecostTax());
        $invoice->setTaxAmount      ($order->getTaxAmount()+$order->getServicecostTax());

        $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal()+$order->getBaseServicecost());
        $invoice->setGrandTotal    ($invoice->getGrandTotal()+$order->getServicecost());

        $invoice->setSubtotalInclTax($invoice->getSubtotalInclTax());
        $invoice->setBaseSubtotalInclTax($invoice->getBaseSubtotalInclTax());
        $invoice->setServicecostPdf($order->getServicecostPdf());

        //Magento will get the totalpaid amount and add the invoiced amount and set the totalpaid to the new value. This results in a double totalPaid value within 		//the order view. This happens only when auto creation of the invoice is disabled. To fix this we will set the Total Paid to 0 before the invoice is created 		//and the totalpaid is update again with the total invoiced.
        //$order->setTotalPaid(0);
        return $this;
    }

}
