<?php

class Mage_Msp_MspPaymentController extends Mage_Core_Controller_Front_Action
{
	/**
	* Notification for the 'old' module, just call the notification for the other controller
	*/
	public function notificationAction() 
	{
		$controllerFile = dirname(__FILE__) . '/StandardController.php';
		include_once($controllerFile);

		$standard = new Mage_Msp_StandardController($this->getRequest(), $this->getResponse());
		$standard->setGatewayModel('gateway_standard');
		$standard->notificationAction();
	}
}
