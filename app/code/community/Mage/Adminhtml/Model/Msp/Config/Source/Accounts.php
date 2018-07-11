<?php

class Mage_Adminhtml_Model_Msp_Config_Source_Accounts
{

	public function toOptionArray()
	{
		return array(
			array(
				"value" => "test",
				"label" => "Test account"
			),
			array(
				"value" => "live",
				"label" => "Live account"
			),
		);
	}

}
