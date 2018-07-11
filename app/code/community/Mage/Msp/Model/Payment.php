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
	
	public function setIdealIssuer($idealissuer)
	{
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
	
	
	/*
	*	Function that will use the fastcheckout xml data to process connect transactions.
	*	For now this will only be used for pay after delivery.
	*/
	public function startPayAfterTransaction()
	{
		$amount = intval((string)($this->getOrder()->getBaseGrandTotal() * 100));
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

		// build request
		$api  = $this->getApi();
		$this->api->test                    = 	($this->getConfigData("test_api") == 'test');
		$api->merchant['notification_url'] 	= 	$this->_notification_url . "?type=initial";
		$api->merchant['cancel_url']       	= 	$this->_cancel_url;
		$api->merchant['redirect_url']     	= 	($this->getConfigData('use_redirect')) ? $this->_return_url : '';

		$api->parseCustomerAddress($billing->getStreet(1));
		$api->customer['locale']           	=	Mage::app()->getLocale()->getDefaultLocale();
		$api->customer['firstname']        	= 	$billing->getFirstname();
		$api->customer['lastname']         	= 	$billing->getLastname();
		$api->customer['address2']         	= 	$billing->getStreet(2);
		$api->customer['zipcode']          	= 	$billing->getPostcode();
		$api->customer['city']             	= 	$billing->getCity();
		$api->customer['state']            	= 	$billing->getState();
		$api->customer['country']          	= 	$billing->getCountry();
		$api->customer['phone']            	= 	$billing->getTelephone();
		$api->customer['email']            	= 	$this->getOrder()->getCustomerEmail();
		
		$api->customer['referrer']			=	$_SERVER['HTTP_REFERER'];
		$api->customer['user_agent']		=	$_SERVER['HTTP_USER_AGENT'];
		$api->customer['ipaddress']			= 	$_SERVER['REMOTE_ADDR'];
		
		$api->gatewayinfo['email']          = 	$this->getOrder()->getCustomerEmail();
		$api->gatewayinfo['phone']			= 	$billing->getTelephone();
		$api->gatewayinfo['bankaccount']    =	'';//not available
		$api->gatewayinfo['referrer']		=	$_SERVER['HTTP_REFERER'];
		$api->gatewayinfo['user_agent']		=	$_SERVER['HTTP_USER_AGENT'];
		$api->gatewayinfo['birthday']		= 	'';//not available
		
		$api->transaction['id']            	= 	$orderId;
		$api->transaction['amount']        	=	$amount;
		$api->transaction['currency']      	= 	"EUR";
		if($conversion)
		{
			$api->transaction['description']   = 'Order #' . $orderId . ' at ' . $storename . '. Original price: ' . round($this->getOrder()->getBaseGrandTotal(), 2) . ' ' . $this->getOrder()->getBaseCurrencyCode();
		}
		else
		{
			$api->transaction['description']   = 'Order #' . $orderId . ' at ' . $storename;
		}
		$api->transaction['gateway']       = 	$this->_gateway;
		$api->transaction['issuer']        = 	$this->_issuer;
		$this->getItems($this->getOrder());
	
		
		$discount_amount = $this->getOrder()->getData();
		//$discount_amount_final	= round($discount_amount['base_discount_amount'],4);
		$discount_amount_final	= number_format($discount_amount['base_discount_amount'], 4, '.', '');
		
		
		//Add discount line item
		$c_item = new MspItem('Discount', 'Discount', 1, $discount_amount_final	, 'KG', 0);// Todo adjust the amount to cents, and round it up.
		$c_item->SetMerchantItemId('Discount');
		$c_item->SetTaxTableSelector('BTW0');
		$this->api->cart->AddItem($c_item);


		 //add none taxtable
		$table 						= 	new MspAlternateTaxTable();
		$table->name				=	'none';
		$rule 						= 	new MspAlternateTaxRule('0.00');
		$table->AddAlternateTaxRules($rule);
		$this->api->cart->AddAlternateTaxTables($table);
		
		$this->api->setDefaultTaxZones();
		
		//Add shipping line item
		$title = $this->getOrder()->getShippingDescription();	
		$price = $this->getOrder()->getShippingAmount();
		$price = number_format($price, 4, '.','');
		$price = (float) Mage::helper('tax')->getShippingPrice($price, false, false);
		$price = number_format($price, 4, '.','');
		if (empty($title) || trim($price)  === '') 
		{
			continue;
		}
		$shipping_tax_id	= 	'none';
		
		foreach($this->_getShippingTaxRules() as $key => $value){
			$shipping_tax_id = $key;
		}
		
		$c_item = new MspItem($title, 'Shipping', 1, $price, 'KG', 0);
		$c_item->SetMerchantItemId('shipping');
		$c_item->SetTaxTableSelector($shipping_tax_id); //TODO Validate this one. 
		$this->api->cart->AddItem($c_item);
		//End shipping line item
		
		
		//Add available taxes to the fco transaction request
		$this->getTaxes();
		
		//ALL data available? Then request the transaction link
		$url = $api->startCheckout();
		
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
			//$this->getOrder()->addStatusToHistory($this->getOrder()->getStatus(), Mage::helper("msp")->__("User redirected to MultiSafepay").'<br/>'.Mage::helper("msp")->__("Payment link:") .'<br/>' . $url);
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

	
	protected function getTaxes()
	{
		$this->_getTaxTable($this->_getShippingTaxRules(), 'default');
		$this->_getTaxTable($this->_getTaxRules(), 'alternate');
		// add 'none' group?
	}

	protected function _getTaxTable($rules, $type)
	{
		if (is_array($rules)) 
		{
			foreach ($rules as $group=>$taxRates) 
			{
				if ($type != 'default') 
				{
					$table = new MspAlternateTaxTable($group, 'true');
					$shippingTaxed = 'false';
				} 
				else 
				{
					$shippingTaxed = 'true';
				}

				if (is_array($taxRates)) 
				{
					foreach ($taxRates as $rate) 
					{
						if ($type != 'default')
						{
							$rule = new MspAlternateTaxRule($rate['value']);
							$rule->AddPostalArea($rate['country']);
							$table->AddAlternateTaxRules($rule);
						}
						else
						{
							$rule = new MspDefaultTaxRule($rate['value'], $shippingTaxed);
							$rule->AddPostalArea($rate['country']);
							$this->api->cart->AddDefaultTaxRules($rule);
						}
					}	
				}	 
				else 
				{
					$taxRate = $taxRates/100;
					if ($type != 'default')
					{
						$rule = new MspAlternateTaxRule($taxRate);
						$rule->SetWorldArea();
						$table->AddAlternateTaxRules($rule);
					}
					else
					{
						$rule = new MspDefaultTaxRule($taxRate, $shippingTaxed);
						$rule->SetWorldArea();
						$this->api->cart->AddDefaultTaxRules($rule);
					}
				}  
				if ($type != 'default')
				{
					$this->api->cart->AddAlternateTaxTables($table);
				}
			}
		} 
		else 
		{
			if (is_numeric($rules)) 
			{
				$taxRate = $rules/100;
				if ($type != 'default')
				{
					$table = new MspAlternateTaxTable();
					$rule = new MspAlternateTaxRule($taxRate);
					$rule->SetWorldArea();
					$table->AddAlternateTaxRules($rule);
					$this->api->cart->AddAlternateTaxTables($table);
                    print_r($table);//Validate this one!
				}
				else
				{
					$rule = new MspDefaultTaxRule($taxRate, 'true');
					$rule->SetWorldArea();
					$this->api->cart->AddDefaultTaxRules($rule);
				}
			}
		}   
	}
  
	protected function _getTaxRules()
	{
		$customerTaxClass = $this->_getCustomerTaxClass();
		if (Mage::helper('tax')->getTaxBasedOn() == 'origin') 
		{
			$request = Mage::getSingleton('tax/calculation')->getRateRequest();
			return Mage::getSingleton('tax/calculation')->getRatesForAllProductTaxClasses($request->setCustomerClassId($customerTaxClass));
		} 
		else 
		{
			$customerRules = Mage::getSingleton('tax/calculation')->getRatesByCustomerTaxClass($customerTaxClass);
			$rules = array();
			foreach ($customerRules as $rule) 
			{
				$rules[$rule['product_class']][] = $rule;
			}
			return $rules;
		}
	}

	protected function _getShippingTaxRules()
	{
		$customerTaxClass = $this->_getCustomerTaxClass();
		
		if ($shippingTaxClass = Mage::getStoreConfig(Mage_Tax_Model_Config::CONFIG_XML_PATH_SHIPPING_TAX_CLASS, $this->getOrder()->getStoreId())) //validate the returned data. Doesn't work with connect pad 
		{
		
			if (Mage::helper('tax')->getTaxBasedOn() == 'origin') 
			{
				$request = Mage::getSingleton('tax/calculation')->getRateRequest();
				$request->setCustomerClassId($customerTaxClass)->setProductClassId($shippingTaxClass);

				return Mage::getSingleton('tax/calculation')->getRate($request);
			}
			$customerRules = Mage::getSingleton('tax/calculation')->getRatesByCustomerAndProductTaxClasses($customerTaxClass, $shippingTaxClass);
			$rules = array();
			foreach ($customerRules as $rule) 
			{
				$rules[$rule['product_class']][] = $rule;
			}
			return $rules;
		} 
		else 
		{
			return array();
		}
	}

	protected function _getCustomerTaxClass()
	{
		$customerGroup = $this->getOrder()->getCustomerGroupId();
		if (!$customerGroup) 
		{
			$customerGroup = Mage::getStoreConfig('customer/create_account/default_group', $this->getOrder()->getStoreId());
		}
		return Mage::getModel('customer/group')->load($customerGroup)->getTaxClassId();
	}
	
	protected function getItems($order)
	{
	
		// we need to get the items from the origional quote as the tax class id isn't available within the product data inside the order.
		$items = Mage::getSingleton('checkout/cart')->getQuote($order->getQuoteId())->getAllItems();


	
		foreach ($items as $item) 
		{
			$product_id = $item->getProductId();
			
			foreach ($order->getAllItems() as $order_item) 
			{
				$order_product_id = $order_item->getProductId();
				if($order_product_id == $product_id){
					$quantity = round($order_item->getQtyOrdered(), 2);
				}
			}
		
			if ($item->getParentItem()) 
			{
				continue;
			}
			$taxClass = ($item->getTaxClassId() == 0 ? 'none' : $item->getTaxClassId());
	
			
			$weight = (float) $item->getWeight();
			$product_id = $item->getProductId();
			
			// name and options
			$itemName = $item->getName();
			$options = $this->getProductOptions($item);
			if (!empty($options))
			{
				$optionString = '';
				foreach ($options as $option) 
				{
					$optionString = $option['label'] . ": " . $option['print_value'] . ",";
				}
				$optionString = substr($optionString, 0, -1);
        
				$itemName .= ' (';
				$itemName .= $optionString;
				$itemName .= ')';
			}
	
			$price	= number_format($item->getPrice(), 4, '.', '');
			//$quantity = round($item->getQtyOrdered(), 2);
		
			// create item
			$c_item = new MspItem($itemName, $item->getDescription(), $quantity, $price, 'KG', $item->getWeight());
			$c_item->SetMerchantItemId($item->getSku());
			$c_item->SetTaxTableSelector($taxClass);
			$this->api->cart->AddItem($c_item);
		}

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
		$this->api->test                    = 	($this->getConfigData("test_api") == 'test');
		$api->merchant['notification_url'] 	= 	$this->_notification_url . "?type=initial";
		$api->merchant['cancel_url']       	= 	$this->_cancel_url;
		$api->merchant['redirect_url']     	= 	($this->getConfigData('use_redirect')) ? $this->_return_url : '';

		$api->parseCustomerAddress($billing->getStreet(1));
		$api->customer['locale']           	=	Mage::app()->getLocale()->getDefaultLocale();
		$api->customer['firstname']        	= 	$billing->getFirstname();
		$api->customer['lastname']         	= 	$billing->getLastname();
		$api->customer['address2']         	= 	$billing->getStreet(2);
		$api->customer['zipcode']          	= 	$billing->getPostcode();
		$api->customer['city']             	= 	$billing->getCity();
		$api->customer['state']            	= 	$billing->getState();
		$api->customer['country']          	= 	$billing->getCountry();
		$api->customer['phone']            	= 	$billing->getTelephone();
		$api->customer['email']            	= 	$this->getOrder()->getCustomerEmail();
		$api->customer['referrer']			=	$_SERVER['HTTP_REFERER'];
		$api->customer['user_agent']		=	$_SERVER['HTTP_USER_AGENT'];
		$api->customer['ipaddress']			= 	$_SERVER['REMOTE_ADDR'];
		$api->transaction['id']            	= 	$orderId;
		$api->transaction['amount']        	=	$amount;
		$api->transaction['currency']      	= 	"EUR";
		if($conversion)
		{
			$api->transaction['description']   = 'Order #' . $orderId . ' at ' . $storename . '. Original price: ' . round($this->getOrder()->getBaseGrandTotal(), 2) . ' ' . $this->getOrder()->getBaseCurrencyCode();
		}
		else
		{
			$api->transaction['description']   = 'Order #' . $orderId . ' at ' . $storename;
		}
		$api->transaction['items']         = 	$items;
		$api->transaction['gateway']       = 	$this->_gateway;
		$api->transaction['issuer']        = 	$this->_issuer;
		
		if($this->_gateway == 'IDEAL' && (isset($_REQUEST['bank']) && !empty($_REQUEST['bank']))){
			$api->extravars					= 	$_REQUEST['bank'];
			$url 							= 	$api->startDirectXMLTransaction();
		}elseif($this->_gateway == 'BANKTRANS')
		{
			/*$api->customer['accountid']				= 	$_SESSION['accountid'];
			$api->customer['accountholdername']		=	$_SESSION['accountholdername'];
			$api->customer['accountholdercity'] 	= 	$_SESSION['accountholdercity'];
			$api->customer['accountholdercountry']	= 	$_SESSION['accountholdercountry'];
			unset($_SESSION['accountid']);
			unset($_SESSION['accountholdername']);
			unset($_SESSION['accountholdercity']);
			unset($_SESSION['accountholdercountry']);*/
			
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
			//$this->getOrder()->addStatusToHistory($this->getOrder()->getStatus(), Mage::helper("msp")->__("User redirected to MultiSafepay").'<br/>'.Mage::helper("msp")->__("Payment link:") .'<br/>' . $url);
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