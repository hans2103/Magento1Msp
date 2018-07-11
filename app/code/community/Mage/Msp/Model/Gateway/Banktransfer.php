<?php

class Mage_Msp_Model_Gateway_Banktransfer extends Mage_Msp_Model_Gateway_Abstract
{
	protected $_code    = "msp_banktransfer";
	protected $_model   = "banktransfer";
	protected $_gateway = "BANKTRANS";
	
	protected $_formBlockType = 'msp/gateways';  
	
	
	public function assignData($data)
	{
		
		if ( !($data instanceof Varien_Object) ) {
			$data = new Varien_Object($data);
		}
			foreach($data as $key => $value)
			{
				if($key == '_data'){
					foreach($value as $index => $val)
					{
					$string .= $index.' - '. $val.'<br />';
						if($index == 'accountid'){
							$accountid = $val;
						}
						elseif($index == 'accountholdername'){
							$accountholdername = $val;
						}
						elseif($index == 'accountholdercity'){
							$accountholdercity = $val;
						}
						elseif($index == 'accountholdercountry'){
							$accountholdercountry = $val;
						}						
					}
				}
			}
		
			$_SESSION['accountid'] = $accountid;
			$_SESSION['accountholdername'] = $accountholdername;
			$_SESSION['accountholdercity'] = $accountholdercity;
			$_SESSION['accountholdercountry'] = $accountholdercountry;

		return $this;
	}
	
	
	
}
