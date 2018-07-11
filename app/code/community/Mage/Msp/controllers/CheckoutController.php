<?php

require_once(Mage::getBaseDir('lib').DS.'multisafepay'.DS.'MultiSafepay.combined.php');

class Mage_Msp_CheckoutController extends Mage_Core_Controller_Front_Action
{
	protected $base;

	/**
	* Checkout redirect -> start checkout transaction
	*/
	public function redirectAction() 
	{
		$session =  Mage::getSingleton('checkout/session');
		$checkout = Mage::getModel("msp/checkout");

		// empty cart -> redirect
		if (!$session->getQuote()->hasItems()) 
		{
			$this->getResponse()->setRedirect(Mage::getUrl('checkout/cart'));
		}
		
		// create new quote
		$storeQuote = Mage::getModel('sales/quote')->setStoreId(Mage::app()->getStore()->getId());
		$storeQuote->merge($session->getQuote());
		$storeQuote->setItemsCount($session->getQuote()->getItemsCount())->setItemsQty($session->getQuote()->getItemsQty())->setChangedFlag(false);
		$storeQuote->save();
    
		$baseCurrency = $session->getQuote()->getBaseCurrencyCode();
		$currency = Mage::app()->getStore($session->getQuote()->getStoreId())->getBaseCurrency();
		$session->getQuote()->collectTotals()->save();
    
		// replace quote into session
		$oldQuote = $session->getQuote();
		$oldQuote->setIsActive(false)->save();
		$session->replaceQuote($storeQuote);
		Mage::getModel('checkout/cart')->init()->save();

		// checkout
		$checkoutLink = $checkout->startCheckout();
    
		header("Location: " . $checkoutLink);
		exit();
	}
	
	function testAction(){
	}
	
	
	/**
	* Agreements page
	*/
	function agreementsAction() 
	{
		$this->loadLayout();	
    	$block = $this->getLayout()->createBlock(
		    'Mage_Checkout_Block_Agreements',
		    '',
			array('template' => 'msp/agreements.phtml')
		);
		echo $block->toHtml();
	}

	/**
	* Return after transaction
	*/
	public function returnAction() 
	{
		$transactionid = $this->getRequest()->getQuery('transactionid');
      
		// clear cart
		$session = Mage::getSingleton("checkout/session");
		$session->unsQuoteId();
		$session->getQuote()->setIsActive(false)->save();

		// set some vars for the success page
		$session->setLastSuccessQuoteId($transactionid);
		$session->setLastQuoteId($transactionid);
		$order = Mage::getSingleton('sales/order')->loadByAttribute('ext_order_id', $transactionid);
		$session->setLastOrderId($order->getId());
		$session->setLastRealOrderId($order->getIncrementId());
		
		$storeId = Mage::app()->getStore()->getId();
		$config = Mage::getStoreConfig('mspcheckout' . "/settings", $storeId);
		
		//We now have an order so we can also request the customerID. With the customer ID we can login the user.
		if($config["auto_login_fco_user"]){
			$order_data =$order->getData();
			$customer = Mage::getModel('customer/customer')->load($order_data['customer_id']);
			$session =Mage::getSingleton('customer/session')->setCustomerAsLoggedIn($customer);
		}
	 
		//Just as an extra feature, option to redirect to the account instead of the thank you page.
		if($config["redirect_to_account"]){
			$this->_redirect("customer/account?utm_nooverride=1", array("_secure" => true));
		}else{
			$this->_redirect("checkout/onepage/success?utm_nooverride=1", array("_secure" => true));
		}
	}
  
	/**
	* Cancel action
	*/
	public function cancelAction() 
	{
		$this->_redirect("checkout", array("_secure" => true));
	}
  
   /**
   * Checks if this is a fastcheckout notification
   */
   function isFCONotification($transId) 
   {
		Mage::log("Checking if FCO notification...", null, "multisafepay.log");
     
		$storeId = Mage::app()->getStore()->getStoreId();
		$config = Mage::getStoreConfig('mspcheckout' . "/settings", $storeId);
   
		$msp = new MultiSafepay();
		$msp->test = ($config["test_api"] == 'test');
		$msp->merchant['account_id'] = $config["account_id"];
		$msp->merchant['site_id'] = $config["site_id"];
		$msp->merchant['site_code'] = $config["secure_code"];
		$msp->transaction['id'] = $transId;
	
		if($msp->getStatus() == false)
		{
			Mage::log("Error while getting status.", null, "multisafepay.log");
		}
		else
		{  
			Mage::log("Got status: ".$msp->details['ewallet']['fastcheckout'], null, "multisafepay.log");
			if($msp->details['ewallet']['fastcheckout'] == "YES")
			{
				return true;
			}
			else
			{			
				return false;
			}
		}
	}
	
	/**
	* Status notification
	*/
	function notificationAction() 
	{
        $transactionid = $this->getRequest()->getQuery('transactionid');
        $initial       = ($this->getRequest()->getQuery('type') == 'initial') ? true : false;
        
        $checkout = Mage::getModel("msp/checkout");
        
        // Check if this is a fastcheckout notification
        if((!$initial) && (!$this->isFCONotification($transactionid))) 
		{
			Mage::log("Redirecting to standard method notification URL...", null, "multisafepay.log");
			$redirect = Mage::getUrl("msp/standard/notification/");
			header('HTTP/1.1 307 Temporary Redirect');
			header('Location: ' . $redirect);
        }
        
        // Is this notification about new shipping address?
        if($this->isShippingMethodsNotification()) 
		{
            $this->handleShippingMethodsNotification($checkout);
            return;
        }
 
 		$done = $checkout->notification($transactionid, $initial);
 		
		if ($initial)
		{
			$returnUrl = Mage::getUrl("msp/checkout/return", array("_secure" => true)) . '?transactionid=' . $transactionid;

			$storeId = Mage::getModel('sales/quote')->load($transactionid)->getStoreId();
			$storeName = Mage::app()->getGroup($storeId)->getName();

 			// display return message
 			$this->getResponse()->setBody('Return to <a href="' . $returnUrl . '">' . $storeName . '</a>');

 		}else{
	 		if ($done)
			{
	 			$this->getResponse()->setBody('ok');
	 		}
			else
			{
	 			$this->getResponse()->setBody('nok');
			}
		} 
	}	

    function isShippingMethodsNotification() 
	{
        // Check for mandatory parameters
        $country = $this->getRequest()->getQuery('country');
        $countryCode = $this->getRequest()->getQuery('countrycode');
        $transactionId = $this->getRequest()->getQuery('transactionid');

        if(empty($country) || empty($countryCode) || empty($transactionId))
		{
            return false;
		}
		else
		{
            return true;
		}
	}
    
    function handleShippingMethodsNotification($model) 
	{
        $country = $this->getRequest()->getQuery('country');
        $countryCode = $this->getRequest()->getQuery('countrycode');
        $transactionId = $this->getRequest()->getQuery('transactionid');
        $weight = $this->getRequest()->getQuery('weight');
        $size = $this->getRequest()->getQuery('size');
        
        header("Content-Type:text/xml");
        print($model->getShippingMethodsFilteredXML($country, $countryCode, $weight, $size, $transactionId));
    }
}