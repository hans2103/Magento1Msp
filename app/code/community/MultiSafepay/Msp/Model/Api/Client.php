<?php

require_once dirname(__FILE__) . "/Object/Orders.php";
require_once dirname(__FILE__) . "/Object/Qwindo.php";
require_once dirname(__FILE__) . "/Object/Issuers.php";
require_once dirname(__FILE__) . "/Object/Gateways.php";
require_once dirname(__FILE__) . "/Object/Affiliates.php";

class Client
{

    public $orders;
    public $qwindo;
    public $issuers;
    public $transactions;
    public $gateways;
    public $affiliates;
    protected $api_key;
    public $api_url;
    public $api_endpoint;
    public $request;
    public $response;
    public $debug;
    public $auth;

    public function __construct()
    {
        $this->orders = new Object_Orders($this);
        $this->qwindo = new Object_Qwindo($this);
        $this->issuers = new Object_Issuers($this);
        $this->gateways = new Object_Gateways($this);
        $this->affiliates = new Object_Affiliates($this);
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function setApiUrl($url)
    {
        $this->api_url = trim($url);
    }

    public function setDebug($debug)
    {
        $this->debug = trim($debug);
    }

    public function setApiKey($api_key)
    {
        $this->api_key = trim($api_key);
    }

    public function processAPIRequest($http_method, $api_method, $http_body = NULL)
    {

        if ($api_method != 'qwindo') {
            if (empty($this->api_key)) {
                throw new Exception("Please configure your MultiSafepay API Key.");
            }
            $url = $this->api_url . $api_method;
            $request_headers = array(
                "Accept: application/json",
                "api_key:" . $this->api_key,
            );
        } else {
            $url = $this->api_url;
            $request_headers = array(
                "Accept: application/json",
                "Auth:" . $this->auth,
            );
        }


        $ch = curl_init($url);

        //echo $url;exit;

        if ($http_body !== NULL) {
            $request_headers[] = "Content-Type: application/json";
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $http_body);
        }

        /* echo $url;
          print_r($request_headers);
          print_r($http_body);exit; */

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_ENCODING, "");
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $http_method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);

        $body = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		
		if($httpcode == null  && !Mage::app()->getStore()->isAdmin()){
			return false;
		}

		

        if ($api_method == 'qwindo' ) {
            if ($httpcode == "401") {
                throw new Exception("Qwindo authorization failed, please check your Qwindo key and Hash ID. Data not updated at Qwindo.");
            }
        }
        if ($this->debug) {
            $this->request = $http_body;
            $this->response = $body;
        }


        if (curl_errno($ch)) {
            if ($api_method != 'qwindo') {

                throw new Exception("Unable to communicate with the MultiSafepay payment server (" . curl_errno($ch) . "): " . curl_error($ch) . ".");
            } else {

				if(Mage::app()->getStore()->isAdmin()){
						throw new Exception("Unable to submit data to qwindo (" . curl_errno($ch) . "): " . curl_error($ch) . ".");
				}else{

	                echo '{
	                    "success": false, 
	                    "data": {
	                        "error_code": "QW-10002",
	                        "error": "Could not create or update the order:' . curl_errno($ch) . "): " . curl_error($ch) . '"
	                        }
	                    }'
	                ;
	                exit;
                }
            }
        }

        return $body;
    }

}
