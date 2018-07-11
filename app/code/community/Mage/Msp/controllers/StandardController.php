<?php

require_once(Mage::getBaseDir('lib').DS.'multisafepay'.DS.'MultiSafepay.combined.php');

class Mage_Msp_StandardController extends Mage_Core_Controller_Front_Action
{
	private $gatewayModel = null;

	/**
	* Set gateway model
	*/
	public function setGatewayModel($model) 
	{
		$this->gatewayModel = $model;
	}
	
	/**
	* Get the current model
	*    - first check if set (gatewayModel)
	*    - check if we have one in the query string
	*    - if not return default
	*/
	public function getGatewayModel() 
	{
		if ($this->gatewayModel)
		{
			return $this->gatewayModel;
		}
		$model = $this->getRequest()->getParam('model');
    
		// filter
		$model = preg_replace("|[^a-zA-Z]+|", "", $model);
	
		if (empty($model)){
			return "gateway_default";
		}else{
			return "gateway_" . $model;
		}
	}
	

	/**
	* Payment redirect -> start transaction
	*/
	public function redirectAction() 
	{
		//TEST -> Saving the quote could be the cause of slow redirect to MSP. Can we keep the cart and save the quote after a transaction and speed up the processing?
		//$this->getOnepage()->getQuote()->setIsActive(true);
		//$this->getOnepage()->getQuote()->save();

		$paymentModel = Mage::getSingleton("msp/" . $this->getGatewayModel());
		if(isset($paymentModel->_gateway)){
			$selected_gateway = $paymentModel->_gateway;
		}
		
		$paymentModel->setParams($this->getRequest()->getParams());
		
		if($selected_gateway != 'PAYAFTER'){
			$paymentLink = $paymentModel->startTransaction();
		}else{
			$paymentLink = $paymentModel->startPayAfterTransaction();
		}		
		
		// redirect
		header("Location: " . $paymentLink);
		exit();
	}
	
	
	/**
	* Testing purposes
	*/
	function testAction() {
	}

	/**
	* Return after transaction
	*/
	public function returnAction() 
	{
		// Fix for emptying cart after success
		$this->getOnepage()->getQuote()->setIsActive(false);
		$this->getOnepage()->getQuote()->save();
		// End fix
		$this->_redirect("checkout/onepage/success?utm_nooverride=1", array("_secure" => true));
	}
	
	public function getOnepage()
	{
		return Mage::getSingleton('checkout/type_onepage');
	}
	
	/**
	* Cancel action
	*/
	public function cancelAction() 
	{
		// cancel order
		$checkout = Mage::getSingleton("checkout/session");
		$order_id = $checkout->getLastRealOrderId();
		$order = Mage::getSingleton('sales/order')->loadByIncrementId($order_id);

		if ($order_id)
		{
			$order->cancel();
			$order->save();
		}
    
		//Validate this function. Do we need this one as an extra setting? Why not just detect it on checkout -> ???
		if (Mage::getStoreConfig("msp/settings/use_onestepcheckout") or Mage::getStoreConfig("payment/msp/use_onestepcheckout"))
		{
			$this->_redirect("onestepcheckout?utm_nooverride=1", array("_secure" => true));
		}else{
			$this->_redirect("checkout?utm_nooverride=1", array("_secure" => true));
		}
	}
	
	
	/**
	* Checks if this is a fastcheckout notification
	*/
	function isFCONotification($transId) 
	{
		Mage::log("Checking if FCO notification...", null, "multisafepay.log");
   
		$storeId = Mage::app()->getStore()->getStoreId();
		$config = Mage::getStoreConfig('mspcheckout' . "/settings", $storeId);
		
		if(isset($config["account_id"]))
		{
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
		}else{
			Mage::log("No FCO transaction so default to normal notification", null, "multisafepay.log");
			return false;
		}
	}  
	

	/**
	* Status notification
	*/
	public function notificationAction() 
	{
		$orderId  = $this->getRequest()->getQuery('transactionid');
		$initial  = ($this->getRequest()->getQuery('type') == 'initial') ? true : false;
			unset($_SESSION['bankid']);
		// Check if this is a fastcheckout notification and redirect
        if((!$initial) && ($this->isFCONotification($orderId))) 
		{
			Mage::log("Redirecting to FCO notification URL...", null, "multisafepay.log");
			$redirect = Mage::getUrl("msp/checkout/notification/");
			header('HTTP/1.1 307 Temporary Redirect');
			header('Location: ' . $redirect);
        }
		
		$paymentModel = Mage::getSingleton("msp/" . $this->getGatewayModel());
		$done = $paymentModel->notification($orderId, $initial);
		
		if ($initial)
		{
			$returnUrl = $paymentModel->getReturnUrl();
			
			$order = Mage::getSingleton('sales/order')->loadByIncrementId($orderId);
			$storename  = $order->getStoreGroupName();

			// display return message
			$this->getResponse()->setBody('Return to <a href="' . $returnUrl . '">' . $storename . '</a>');	
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
}