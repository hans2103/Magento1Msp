<?php
require_once(Mage::getBaseDir('lib').DS.'multisafepay'.DS.'MultiSafepay.combined.php');

class Mage_Msp_Model_Base extends Varien_Object
{
	protected $_config;
	protected $_order 			= null;
	protected $_lockId   		= null;
	protected $_lockCode 		= 'msp';
	protected $_isLocked 		= null;
	protected $_lockFile 		= null;
	protected $_lockFilename 	= null;
	protected $_logId 			= null;
	protected $_logFileName 	= 'multisafepay.log';
	public $api 				= null;

	/**
	* Set the config object of the Base
	*/
	public function setConfigObject($config)
	{
		$this->_config 			= $config;
		return $this;
	}

	function getConfigData($name)
	{
		if(isset($this->_config[$name]))
		{
			return $this->_config[$name];
		}else{
			return false;
		}
	}

	/**
	* Logging functions
	*/
	function isDebug()
	{
		return $this->getConfigData('debug');
	}

	function setLogId($id = null)
	{
		$this->_logId 			= $id;
	}
	
	function log()
	{
		$argv   				= func_get_args();
		$data   				= array_shift($argv);

		if (is_string($data))
		{
			$logData 			= @vsprintf($data, $argv);

			// if vsprintf failed, just use the data
			if (!$logData)
			{
				$logData 		= $data;
			}
			if ($this->_logId)
			{
				$logData 		= '[' . $this->_logId . '] ' . $logData;
			}
			
		}else{
			$logData 			= $data;
		}

		if ($this->isDebug())
		{
			Mage::log($logData, null, $this->_logFileName);
		}
	}


	/**
	* Returns an instance of de Api and set some standard settings
	*/
	public function getApi()
	{
		if ($this->api)
		{
			return $this->api;
		}
		
		$this->api = new MultiSafepay();
		$this->api->plugin_name             	= 'Magento';
		$this->api->version                 	= Mage::getConfig()->getNode('modules/Mage_Msp/version');
		$this->api->use_shipping_notification 	= false;
		$this->api->test                    	= ($this->getConfigData("test_api") == 'test');
		$this->api->merchant['account_id']  	= $this->getConfigData("account_id");
		$this->api->merchant['site_id']     	= $this->getConfigData("site_id");
		$this->api->merchant['site_code']   	= $this->getConfigData("secure_code");

		return $this->api;
	}

	/**
	* Update an order according to the specified MultiSafepay status
	*/
	public function updateStatus($order, $mspStatus, $mspDetails = array())
	{
		$orderSaved = false;
		$statusInitialized 					= $this->getConfigData("initialized_status");
		$statusComplete    					= $this->getConfigData("complete_status");
		$statusUncleared   					= $this->getConfigData("uncleared_status");
		$statusVoid        					= $this->getConfigData("void_status");
		$statusDeclined    					= $this->getConfigData("declined_status");
		$statusExpired     					= $this->getConfigData("expired_status");
		$autocreateInvoice 					= $this->getConfigData("autocreate_invoice");

		/*
		const STATE_NEW             = 'new';
		const STATE_PENDING_PAYMENT = 'pending_payment';
 		const STATE_PROCESSING      = 'processing';
		const STATE_COMPLETE        = 'complete';
		const STATE_CLOSED          = 'closed';
		const STATE_CANCELED        = 'canceled';
		const STATE_HOLDED          = 'holded';
		*/

		$complete  = false;
		$cancel    = false;
		$newState  = null;
		$newStatus = true; // makes Magento use the default status belonging to state
		$statusMessage   = '';
		switch ($mspStatus) 
		{
			case "initialized":
				$newState = Mage_Sales_Model_Order::STATE_NEW;
				$newStatus = $statusInitialized;
				$statusMessage = Mage::helper("msp")->__("Transaction started, waiting for payment");
			break;
			case "completed":
				$complete = true;
				$newState = Mage_Sales_Model_Order::STATE_PROCESSING;
				$newStatus = $statusComplete;
				$statusMessage = Mage::helper("msp")->__("Payment Completed");
			break;
			case "uncleared":
				$newState = Mage_Sales_Model_Order::STATE_NEW;
				$newStatus = $statusUncleared;
				$statusMessage = Mage::helper("msp")->__("Transaction started, waiting for payment");
			break;
			case "void":
				$cancel = true;
				$newState = Mage_Sales_Model_Order::STATE_CANCELED;
				$statusMessage = Mage::helper("msp")->__("Transaction voided");
			break;
			case "declined":
				$cancel = true;
				$newState = Mage_Sales_Model_Order::STATE_CANCELED;
				$statusMessage = Mage::helper("msp")->__("Transaction declined");
			break;
			case "expired":
				$cancel = true;
				$newState = Mage_Sales_Model_Order::STATE_CANCELED;
				$statusMessage = Mage::helper("msp")->__("Transaction is expired");
			break;
			default:
			$statusMessage = Mage::helper("msp")->__("Status not found " . $mspStatus);
			return false;
		}

		// create the status message
		$paymentType = '';
		if (!empty($mspDetails['paymentdetails']['type']) )
		{
			$paymentType = Mage::helper("msp")->__("Payment Type: <strong>%s</strong>", $mspDetails['paymentdetails']['type']).'<br/>';
		}

		$statusMessage .= '<br/>'.Mage::helper("msp")->__("Status: <strong>%s</strong>", $mspStatus).'<br/>'.$paymentType;

		// only update from certain states //
		$current_state = $order->getState();
		
		$canUpdate = false;
		
		
		//foreach($order->getItemsCollection() as $item)    
			//{        
                //echo $item->getId();exit;
				/*$current = $item->getQty();
					
				$new = $current - $item->getQtyCanceled() ;
				
				$product = Mage::getModel('catalog/product')->loadByAttribute('id', $item->getId());

				$stock_obj = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product->getId());

				$stockData = $stock_obj->getData();
				$stockData['qty'] = $new;
				$stock_obj->setData($stockData);
				$stock_obj->save();*/
				
					//$current = $item->getQty();
					
					//$new = $current - $item->getQtyCanceled() ;
				
					//$item->setQtyCanceled(0)->save(); 
					//$item->setStockData($new);
					
									
          //  }
		
		/*
		*	 TESTING UNDO CANCEL
		*
		*	Start undo cancel function
		*/
		if($current_state == Mage_Sales_Model_Order::STATE_CANCELED && $newState != Mage_Sales_Model_Order::STATE_CANCELED)
		{
            foreach($order->getItemsCollection() as $item)    
			{        
                if ($item->getQtyCanceled() > 0){
					$item->setQtyCanceled(0)->save();
				}					
            }
			
			$products = $order->getAllItems();

			foreach ($products as $itemId => $product)
			{
				$id = $product->getProductId();
				$stock_obj = Mage::getModel('cataloginventory/stock_item')->loadByProduct($id);
				$stockData = $stock_obj->getData();
						
				$new = $stockData['qty'] - $product->getQtyOrdered();
				$stockData['qty'] = $new;
				$stock_obj->setData($stockData);
				$stock_obj->save();
			}
    
            $order
                ->setBaseDiscountCanceled(0)
                ->setBaseShippingCanceled(0)
                ->setBaseSubtotalCanceled(0)
                ->setBaseTaxCanceled(0)
                ->setBaseTotalCanceled(0)
                ->setDiscountCanceled(0)
                ->setShippingCanceled(0)
                ->setSubtotalCanceled(0)
                ->setTaxCanceled(0)
                ->setTotalCanceled(0);
        
            $state = 'new';
            $status = 'pending';
			

			$order
				->setStatus($status)
				->setState($state)
				->save();
            
            $order->addStatusToHistory($status, 'Order has been reopened because a new transaction was started by the customer!'); 
		}
		
		/*
		*	ENDING UNDO CANCEL CODE
		*/
		if($order->getState() == Mage_Sales_Model_Order::STATE_PROCESSING){
			$is_already_invoiced = true;
		}else{
			$is_already_invoiced = false;
		}
		
		if ($order->getState() == Mage_Sales_Model_Order::STATE_NEW )// test without || $order->getState() == Mage_Sales_Model_Order::STATE_PROCESSING to avoid duplicate cancel bug
		{
			$canUpdate = true;
		}

		// update the status if changed
		if ($canUpdate && (($newState != $order->getState()) || ($newStatus != $order->getStatus())))
		{
			$order->setState($newState, $newStatus, $statusMessage);

			// create an invoice when the payment is completed
			if ($complete && $autocreateInvoice && !$is_already_invoiced)
			{
				$this->createInvoice($order);
			}
		}else{
			// add status to history if it's not there
			if (!$this->isStatusInHistory($order, $mspStatus) && (ucfirst($order->getState()) != ucfirst(Mage_Sales_Model_Order::STATE_CANCELED)))
			{
				$order->addStatusToHistory($order->getStatus(), $statusMessage);
			}
		}

		if(ucfirst($order->getState()) == ucfirst(Mage_Sales_Model_Order::STATE_CANCELED))
		{
			if (!$this->isCancellationFinal($order, 'Cancellation finalized'))
			{
				$order->setState('canceled', 'canceled', 'Cancellation finalized');
				$order->cancel();
			}
		}
		
		
		/**
		*	Fix to activate new order email function to be activated
		*/
		$send_order_email = $this->getConfigData("new_order_mail");
		
		if($send_order_email == 'after_payment')
		{
			if (!$order->getEmailSent() && (ucfirst($order->getState()) == ucfirst(Mage_Sales_Model_Order::STATE_PROCESSING)))
			{
				$order->setEmailSent(true);
				$order->save();
				$orderSaved = true;
				$order->sendNewOrderEmail();
			}
		}
		elseif($send_order_email =='after_notify_without_cancel' && (ucfirst($order->getState()) != ucfirst(Mage_Sales_Model_Order::STATE_CANCELED)))
		{
			if (!$order->getEmailSent())
			{
				$order->setEmailSent(true);
				$order->save();
				$orderSaved = true;
				$order->sendNewOrderEmail();
			}
		}elseif($send_order_email =='after_notify_with_cancel')
		{
			if (!$order->getEmailSent())
			{
				$order->setEmailSent(true);
				$order->save();
				$orderSaved = true;
				$order->sendNewOrderEmail();
			}
		}
		// save order if we haven't already
		if (!$orderSaved)
		{
			$order->save();
		}
	
		// success
		return true;
	}

	/**
	* Check if a certain MultiSafepay status is already in the order history (to prevent doubles)
	*/
	function isStatusInHistory($order, $mspStatus)
	{
		$history = $order->getAllStatusHistory();
		foreach($history as $status)
		{
			if(strpos($status->getComment(), 'Status: <strong>'.$mspStatus.'</strong>') !== false)
			{
				return true;
			}
		}
		return false;
	}
	
	/**
	* Check if a certain MultiSafepay status is already in the order history (to prevent doubles)
	*/
	function isCancellationFinal($order, $mspStatus)
	{
		$history = $order->getAllStatusHistory();
		
		foreach($history as $status)
		{
			if(strpos($status->getComment(), $mspStatus) !== false)
			{
				return true;
			}
		}
		return false;
	}
	

	/**
	* Get the current Magento version (as integer, 1.4.x.x => 14)
	*/
	private function getMagentoVersion()
	{
		$version = Mage::getVersion();
		$arr = explode('.', $version);
		return $arr[0] . $arr[1];
	}

	/**
	*  Create invoice for order
	*/
	protected function createInvoice(Mage_Sales_Model_Order $order)
	{
		if ($order->canInvoice() && !$order->getInvoiceCollection()->getSize()) 
		{
			$invoice = $order->prepareInvoice();
			$invoice->register();

			// hack for 1.3
			if ($this->getMagentoVersion() <= 13){ //  <= 1.3.x.x
				$invoice->capture();
			}else{
				$invoice->pay();
			}

			$invoice->save();

			$transactionSave = Mage::getModel('core/resource_transaction')->addObject($invoice)->addObject($invoice->getOrder());
			$transactionSave->save();
			
			$mail_invoice = $this->getConfigData("mail_invoice");
			if ($mail_invoice)
			{
				$invoice->setEmailSent(true);
				$invoice->save();
				$invoiceSaved = true;
				$invoice->sendEmail();
			}
			// save invoice if we haven't already
			if (!$invoiceSaved)
			{
				$invoice->save();
			}
			return true;
		}
		return false;
	}

	/**
	*  Get lock file
	*/
	protected function _getLockFile()
	{
		if($this->_lockFile === null) 
		{
			$varDir = Mage::getConfig()->getVarDir('locks');
			$this->lockFilename = $varDir . DS . $this->_lockCode . '_' . $this->_lockId . '.lock';
			if (is_file($this->lockFilename)) 
			{
				$this->_lockFile = fopen($this->lockFilename, 'w');
			} else {
				$this->_lockFile = fopen($this->lockFilename, 'x');
			}
			fwrite($this->_lockFile, date('r'));
		}
		return $this->_lockFile;
	}


	/**
	*  Set some lock vars
	*/
	function setLockId($id = null)
	{
		$this->_lockId = $id;
	}

	function setLockCode($code = null)
	{
		$this->_lockCode = $code;
	}

	/**
	*  Create lock
	*/
	public function lock()
	{
		$this->_isLocked = true;
		flock($this->_getLockFile($this->_lockId), LOCK_EX | LOCK_NB);
		return $this;
	}

	/**
	*  Prevent deletion of lockfile
	*/
	public function preventLockDelete()
	{
		$this->_lockFile = null;
	}

	/**
	*  Unlock
	*/
	public function unlock()
	{
		$this->_isLocked = false;
		flock($this->_getLockFile($this->_lockId), LOCK_UN);
		return $this;
	}

	/**
	*  Check if locked
	*/
	public function isLocked()
	{
		if ($this->_isLocked !== null) 
		{
			return $this->_isLocked;
		} else {
			$fp = $this->_getLockFile($this->_lockId);
			if (flock($fp, LOCK_EX | LOCK_NB)) 
			{
				flock($fp, LOCK_UN);
				return false;
			}
			return true;
		}
	}

	/**
	*  Destroy lock file on destuct
	*/
	public function __destruct()
	{
		if ($this->_lockFile) 
		{
			fclose($this->_lockFile);
			unlink($this->lockFilename);
		}
	}
}