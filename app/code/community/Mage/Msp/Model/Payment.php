<?php

require_once(Mage::getBaseDir('lib').DS.'multisafepay'.DS.'MultiSafepay.combined.php');

class Mage_Msp_Model_Payment extends Varien_Object
{
	protected $_config;
	protected $_gateway;
	protected $_issuer;
	protected $_idealissuer;
	protected $_notification_url;
	protected $_cancel_url;
	protected $_return_url;
	protected $_order = null;
	public $base;
	public $api;
	
	public $pay_factor = 1;

	/**
	* Set some vars
	*/
	public function setNotificationUrl($url)
	{
		$this->_notification_url = $url;
	}

	public function setReturnUrl($url)
	{
		$this->_return_url = $url;
	}

	public function setCancelUrl($url)
	{
		$this->_cancel_url = $url;
	}

	public function setGateway($gateway)
	{
		$this->_gateway = $gateway;
	}
	
	public function setIdealIssuer($idealissuer){
		$this->_idealissuer = $idealissuer;
	}
	
	public function setIssuer($issuer)
	{
		$this->_issuer = $issuer;
	}


	/**
	* Set the config object
	*/
	public function setConfigObject($config)
	{
		$this->_config = $config;
		return $this;
	}

	function getConfigData($name)
	{
		if(isset($this->_config[$name]))
		{
			return $this->_config[$name];
		}
		else
		{
			return false;
		}
	}


	/**
	* Returns an instance of the Base
	*/
	public function getBase($id = null)
	{
		if ($this->base)
		{
			if ($id)
			{
				$this->base->setLogId($id);
				$this->base->setLockId($id);
			}	
			return $this->base;
		}

		$this->base = Mage::getSingleton("msp/base");
		$this->base->setConfigObject($this->_config);
		$this->base->setLogId($id);
		$this->base->setLockId($id);

		return $this->base;
	}

	/**
	* Returns an instance of the Api
	*/
	public function getApi($id = null)
	{
		if ($this->api)
		{
			if ($id)
			{
				$this->getBase($id);
			}
			return $this->api;
		}

		$base      = $this->getBase($id);
		$this->api = $base->getApi();

		return $this->api;
	}


	/**
	* Get the current order object
	*/
	public function getOrder() 
	{
		if ($this->_order == null)
		{
			$orderIncrementId = $this->getCheckout()->getLastRealOrderId();
			$this->_order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
		}
		return $this->_order;
	}


	/**
	* Get the checkout order object
	*/
	public function getCheckout() 
	{
		return Mage::getSingleton("checkout/session");
	}

	

	/**
	* Get the gateway list
	*/
	public function getGateways()
	{
		$billing = $this->getCheckout()->getQuote()->getBillingAddress();
		if ($billing)
		{
			$country = $billing->getCountry();
		}
		else
		{
			$country = "NL";
		}

		$api = $this->api;
		$api->customer['country'] = $country;

		// get the gateways
		$gateways = $api->getGateways();

		if ($api->error)
		{
			// let's not crash on a error with the gateway request
			return array();
		}
		return $gateways;
	}

	/**
	* Send a transaction request to MultiSafepay and return the payment_url
	*/
	public function startTransaction()
	{
		// amount
		$amount = intval((string)($this->getOrder()->getBaseGrandTotal() * 100));

		// factor
		$amount = round($amount * $this->pay_factor);

		// only euro
		$conversion = false;
		if ($this->getOrder()->getBaseCurrencyCode() != "EUR")
		{
			$fromCur = $this->getOrder()->getBaseCurrencyCode();
			$conversion = true;
			$amount = round(Mage::helper('directory')->currencyConvert($amount, $fromCur, 'EUR'));
		}

		// storename
		$storename  = $this->getOrder()->getStoreGroupName();

		// order id
		$orderId = $this->getCheckout()->getLastRealOrderId();
		
		// addresses
		$billing  = $this->getOrder()->getBillingAddress();
		$shipping = $this->getOrder()->getShippingAddress();

		// generate items list
		$items = "<ul>\n";
		foreach ($this->getOrder()->getAllItems() as $item) 
		{
			if ($item->getParentItem())
			{
					continue;
			}
			$items .= "<li>" . ($item->getQtyOrdered()*1) . " x : " . $item->getName() . "</li>\n";
		}
		$items .= "</ul>\n";

		// build request
		$api  = $this->getApi();
		$api->merchant['notification_url'] = $this->_notification_url . "?type=initial";
		$api->merchant['cancel_url']       = $this->_cancel_url;
		$api->merchant['redirect_url']     = ($this->getConfigData('use_redirect')) ? $this->_return_url : '';

		$api->parseCustomerAddress($billing->getStreet(1));
		$api->customer['locale']           = Mage::app()->getLocale()->getDefaultLocale();
		$api->customer['firstname']        = $billing->getFirstname();
		$api->customer['lastname']         = $billing->getLastname();
		$api->customer['address2']         = $billing->getStreet(2);
		$api->customer['zipcode']          = $billing->getPostcode();
		$api->customer['city']             = $billing->getCity();
		$api->customer['state']            = $billing->getState();
		$api->customer['country']          = $billing->getCountry();
		$api->customer['phone']            = $billing->getTelephone();
		$api->customer['email']            = $this->getOrder()->getCustomerEmail();
	
		$api->transaction['id']            = $orderId;
		$api->transaction['amount']        = $amount;
		$api->transaction['currency']      = "EUR";
		if($conversion)
		{
			$api->transaction['description']   = 'Order #' . $orderId . ' at ' . $storename . '. Original price: ' . round($this->getOrder()->getBaseGrandTotal(), 2) . ' ' . $this->getOrder()->getBaseCurrencyCode();
		}
		else
		{
			$api->transaction['description']   = 'Order #' . $orderId . ' at ' . $storename;
		}
		$api->transaction['items']         = $items;
		$api->transaction['gateway']       = $this->_gateway;
		$api->transaction['issuer']        = $this->_issuer;
		
		
		if($this->_gateway == 'IDEAL' && isset($_SESSION['bankid'])){
			$api->extravars					= 	$_SESSION['bankid'];
			unset($_SESSION['bankid']);
			$url 							= 	$api->startDirectXMLTransaction();
		}elseif($this->_gateway == 'BANKTRANS')
		{
			$api->customer['accountid']				= 	$_SESSION['accountid'];
			$api->customer['accountholdername']		=	$_SESSION['accountholdername'];
			$api->customer['accountholdercity'] 	= 	$_SESSION['accountholdercity'];
			$api->customer['accountholdercountry']	= 	$_SESSION['accountholdercountry'];
			unset($_SESSION['accountid']);
			unset($_SESSION['accountholdername']);
			unset($_SESSION['accountholdercity']);
			unset($_SESSION['accountholdercountry']);
			
			//print_r($api);exit;
			$data =$api->startDirectBankTransfer();
			$url = Mage::getUrl("checkout/onepage/success?utm_nooverride=1", array("_secure" => true));
		}else
		{
			$url 							= 	$api->startTransaction();
		}
	
		$this->getBase($orderId)->log($api->request_xml);
		$this->getBase($orderId)->log($api->reply_xml);

		// error
		if ($api->error)
		{
			$this->getBase()->log("Error %s: %s", $api->error_code, $api->error);

			// add error status history
		$this->getOrder()->addStatusToHistory($this->getOrder()->getStatus(), Mage::helper("msp")->__("Error creating transaction").'<br/>'.$api->error_code . " - " . $api->error);
		$this->getOrder()->save();

		// raise error
		Mage::throwException(Mage::helper("msp")->__("An error occured: ") . $api->error_code . " - " . $api->error);
		}

		// save payment link to status history
		if ($this->getConfigData("save_payment_link") || true)
		{
			$this->getOrder()->addStatusToHistory($this->getOrder()->getStatus(), Mage::helper("msp")->__("User redirected to MultiSafepay").'<br/>'.Mage::helper("msp")->__("Payment link:") .'<br/>' . $url);
			$this->getOrder()->save();
		}
		
		$send_order_email = $this->getConfigData("new_order_mail");
		if($send_order_email == 'after_confirmation')
		{
			if (!$this->getOrder()->getEmailSent())
			{
				$this->getOrder()->setEmailSent(true);
				$this->getOrder()->save();
				$this->getOrder()->sendNewOrderEmail();
			}
		}
		return $url;
	}

	function notification($orderId, $initial = false)
	{
		// get the order
		$order = Mage::getSingleton('sales/order')->loadByIncrementId($orderId);
	
		$base = $this->getBase($orderId);
	
		// check lock
		if ($base->isLocked())
		{
			$base->preventLockDelete();
	
			if ($initial)
			{
				return;
			}
			else
			{
				echo 'locked';
				exit();
			}
		}

		// lock
		$base->lock();

		// get the status
		$api = $this->getApi($orderId);
		$api->transaction['id'] = $orderId;
		$status = $api->getStatus();

		if ($api->error)
		{
			$base->unlock();
			Mage::throwException(Mage::helper("msp")->__("An error occured: ") . $api->error_code . " - " . $api->error);
			echo 'Error : ' . $api->error_code . " - " . $api->error;
			exit();
		}

		// determine status
		$status = strtolower($status);

		// update orderstatus in Magento
		$ret = $base->updateStatus($order, $status, $api->details);

		// unlock
		$base->unlock();

		return $ret;
	}
}