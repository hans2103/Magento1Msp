<?php

class Mage_Adminhtml_Model_Msp_Config_Source_Order_Email
{

	public function toOptionArray()
	{
		return array(
			array(
				"value" => "after_confirmation",
				"label" => Mage::helper("msp")->__("After order confirmation")
			),
			array(
				"value" => "after_payment",
				"label" => Mage::helper("msp")->__("After payment complete")
			),
			array(
				"value" => "after_notify_with_cancel",
				"label" => Mage::helper("msp")->__("After notification, including cancelled order")
			),
			array(
				"value" => "after_notify_without_cancel",
				"label" => Mage::helper("msp")->__("After notification, excluding cancelled order")
			)
		);
	}

}
