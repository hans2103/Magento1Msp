<?php

/**
 *
 * @category MultiSafepay
 * @package  MultiSafepay_Msp
 */
require_once(Mage::getBaseDir('lib') . DS . 'multisafepay' . DS . 'MultiSafepay.combined.php');

class MultiSafepay_Msp_StandardController extends Mage_Core_Controller_Front_Action {

    private $gatewayModel = null;

    /**
     * Set gateway model
     */
    public function setGatewayModel($model) {
        $this->gatewayModel = $model;
    }

    /**
     * Get the current model
     *    - first check if set (gatewayModel)
     *    - check if we have one in the query string
     *    - if not return default
     */
    public function getGatewayModel() {
        if ($this->gatewayModel) {
            return $this->gatewayModel;
        }


        $orderId = $this->getRequest()->getQuery('transactionid');
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderId); //use a real increment order id here

        $model = $this->getRequest()->getParam('model');

        // filter
        $model = preg_replace("|[^a-zA-Z]+|", "", $model);

        if (empty($model)) {
            if ($orderId == '') {
                return "gateway_default";
            } else {
                if (is_object($order->getPayment())) {
                    $model = $order->getPayment()->getMethodInstance()->_model;
                    if ($model == '') {
                        return "gateway_default";
                    } else {
                        return "gateway_" . $model;
                    }
                } else {
                    return "gateway_default";
                }
            }
        } else {
            return "gateway_" . $model;
        }
    }

    /**
     * Payment redirect -> start transaction
     */
    public function redirectAction() {
        $paymentModel = Mage::getSingleton("msp/" . $this->getGatewayModel());
        $selected_gateway = '';
        if (isset($paymentModel->_gateway)) {
            $selected_gateway = $paymentModel->_gateway;
        }

        $paymentModel->setParams($this->getRequest()->getParams());

        if ($selected_gateway != 'PAYAFTER' && $selected_gateway != 'KLARNA' && $selected_gateway != 'EINVOICE') {
            $paymentLink = $paymentModel->startTransaction();
        } else {
            $paymentLink = $paymentModel->startPayAfterTransaction();
        }

        //header("Location: " . $paymentLink);

        header('Content-type: text/html; charset=utf-8');
        header("Location: " . $paymentLink, true);
        header("Connection: close", true);
        header("Content-Length: 0", true);
        flush();
        @ob_flush();

        exit();
    }

    /**
     * Return after transaction
     */
    public function returnAction() {
        $transactionId = $this->getRequest()->getQuery('transactionid');

        /** @var $session Mage_Checkout_Model_Session */
        $session = Mage::getSingleton("checkout/session");
        $session->unsQuoteId();
        $session->getQuote()->setIsActive(false)->save();

        // set some vars for the success page
        $session->setLastSuccessQuoteId($transactionId);
        $session->setLastQuoteId($transactionId);

        /** @var $order Mage_Sales_Model_Order */
        //$order = Mage::getSingleton('sales/order')->loadByAttribute('ext_order_id', $transactionId);
        $order = Mage::getModel('sales/order')->loadByIncrementId($transactionId);
        $session->setLastOrderId($order->getId());
        $session->setLastRealOrderId($order->getIncrementId());

        //$url = Mage::getUrl('checkout/onepage/success?utm_nooverride=1&__store', array("__secure" => true, "__store"=> $order->getStoreId()));
        /* $url = Mage::getUrl('checkout/onepage/success', array(
          '_current' => true,
          '_use_rewrite' => true,
          '_secure' => true,
          '_store' => $order->getStoreId(),
          '_store_to_url' => true,
          'query' => array("utm_nooverride" => 1)
          )); */


        $this->_redirect("checkout/onepage/success", array(
            '_current' => true,
            '_use_rewrite' => true,
            '_secure' => true,
            '_store' => $order->getStoreId(),
            '_store_to_url' => true,
            '_query' => array("utm_nooverride" => 1)
        ));


        //print_r($url);exit;

        /* header('Content-type: text/html; charset=utf-8');
          header("Location: " . $url, true);
          header("Connection: close", true);
          header("Content-Length: 0", true);
          exit; */
        //$this->_redirect($url);
        //$this->_redirect("checkout/onepage/success?utm_nooverride=1", array("__secure" => true, "__store"=> $order->getStoreId()));
    }

    /**
     * @return Mage_Checkout_Model_Type_Onepage
     */
    public function getOnepage() {
        return Mage::getSingleton('checkout/type_onepage');
    }

    /**
     * Cancel action
     */
    public function cancelAction() {
        // cancel order
        $checkout = Mage::getSingleton("checkout/session");
        $order_id = $checkout->getLastRealOrderId();
        $order = Mage::getSingleton('sales/order')->loadByIncrementId($order_id);

        if ($order_id) {
            $order->cancel();
            $order->save();
        }


        $quote = Mage::getModel('sales/quote')->load($checkout->getLastQuoteId());

        //add keep cart function in cancelaction to have better support for onestepcheckout modules that overrule the observer
        if (Mage::getStoreConfig('payment/msp/keep_cart', $quote->getStoreId()) ||
                Mage::getStoreConfig('msp/settings/keep_cart', $quote->getStoreId()) ||
                $quote->getPayment()->getMethod() == 'msp_payafter' ||
                $quote->getPayment()->getMethod() == 'msp_einvoice' ||
                $quote->getPayment()->getMethod() == 'msp_klarna') {

            if ($quoteId = $checkout->getLastQuoteId()) {
                $quote = Mage::getModel('sales/quote')->load($quoteId);
                if ($quote->getId()) {
                    $quote->setIsActive(true)->save();
                    $checkout->setQuoteId($quoteId);
                }
            }
        }


        //Validate this function. Do we need this one as an extra setting? Why not just detect it on checkout -> ???
        if (Mage::getStoreConfig("msp/settings/use_onestepcheckout") || Mage::getStoreConfig("payment/msp/use_onestepcheckout")) {
            //$this->_redirect("onestepcheckout?utm_nooverride=1", array("_secure" => true));
            $this->_redirect("onestepcheckout", array("_secure" => true,
                "query" => array("utm_nooverride" => 1)));
        } else {
            //$this->_redirect("checkout?utm_nooverride=1", array("_secure" => true));
            $this->_redirect("checkout", array("_secure" => true,
                "query" => array("utm_nooverride" => 1)));
        }
    }

    /**
     * Checks if this is a fastcheckout notification
     */
    public function isFCONotification($transId) {
        //Mage::log("Checking if FCO notification...", null, "multisafepay.log");

        /** @var $quote Mage_Sales_Model_Quote */
        $quote = Mage::getModel('sales/quote')->load($transId);

        $storeId = Mage::app()->getStore()->getStoreId();
        if ($quote) {
            $storeId = $quote->getStoreId();
        }

        $config = Mage::getStoreConfig('mspcheckout/settings', $storeId);

        if (isset($config['account_id']) && isset($config['test_api']) &&
                isset($config['site_id']) && isset($config['secure_code'])) {

            $msp = new MultiSafepay();
            $msp->test = ($config["test_api"] == 'test');
            $msp->merchant['account_id'] = $config["account_id"];
            $msp->merchant['site_id'] = $config["site_id"];
            $msp->merchant['site_code'] = $config["secure_code"];
            $msp->transaction['id'] = $transId;

            if ($msp->getStatus() == false) {
                //Mage::log("Error while getting status.", null, "multisafepay.log");
            } else {
                //Mage::log("Got status: ".$msp->details['ewallet']['fastcheckout'], null, "multisafepay.log");
                if ($msp->details['ewallet']['fastcheckout'] == "YES") {
                    return true;
                } else {
                    return false;
                }
            }
        } else {
            // Mage::log("No FCO transaction so default to normal notification", null, "multisafepay.log");
            return false;
        }
    }

    /**
     * Status notification
     */
    public function notificationAction($return = false) {
        $orderId = $this->getRequest()->getQuery('transactionid');
        $initial = ($this->getRequest()->getQuery('type') == 'initial') ? true : false;
        $transactionid = $this->getRequest()->getQuery('transactionid');

        // Check if this is a fastcheckout notification and redirect
        //check if FCO transaction
        $storeId = Mage::app()->getStore()->getStoreId();
        $config = Mage::getStoreConfig('mspcheckout' . "/settings", $storeId);

        if (isset($config["active"]) && $config["active"]) {//if (isset($config["account_id"])) {
            $msp = new MultiSafepay();
            $msp->test = ($config["test_api"] == 'test');
            $msp->merchant['account_id'] = $config["account_id"];
            $msp->merchant['site_id'] = $config["site_id"];
            $msp->merchant['site_code'] = $config["secure_code"];
            $msp->transaction['id'] = $transactionid;

            if ($msp->getStatus() == false) {
                //Mage::log("Error while getting status.", null, "multisafepay.log");
            } else {
                if ($msp->details['ewallet']['fastcheckout'] == "YES") {
                    $transactionid = $this->getRequest()->getQuery('transactionid');
                    $initial = ($this->getRequest()->getQuery('type') == 'initial') ? true : false;
                    $checkout = Mage::getModel("msp/checkout");
                    $done = $checkout->notification($transactionid, $initial);

                    if ($initial) {
                        $returnUrl = Mage::getUrl("msp/checkout/return", array("_secure" => true)) . '?transactionid=' . $transactionid;

                        $storeId = Mage::getModel('sales/quote')->load($transactionid)->getStoreId();
                        $storeName = Mage::app()->getGroup($storeId)->getName();

                        // display return message
                        echo 'Return to <a href="' . $returnUrl . '?transactionid=' . $orderId . '">' . $storeName . '</a>';
                    } else {
                        if ($done) {
                            echo 'ok';
                        } else {
                            echo 'ng';
                        }
                    }
                    exit;
                }
            }
        }
        $paymentModel = Mage::getSingleton("msp/" . $this->getGatewayModel());

        $done = $paymentModel->notification($orderId, $initial);

        if (!$return) {
            if ($initial) {
                $returnUrl = $paymentModel->getReturnUrl();

                $order = Mage::getSingleton('sales/order')->loadByIncrementId($orderId);
                $storename = $order->getStoreGroupName();

                // display return message
                $this->getResponse()->setBody('Return to <a href="' . $returnUrl . '?transactionid=' . $orderId . '">' . $storename . '</a>');
            } else {
                if ($done) {
                    $this->getResponse()->setBody('ok');
                } else {
                    $this->getResponse()->setBody('ng');
                }
            }
        } else {
            return true;
        }
    }

    /*
     * 	Function that generates a JSON product feed based on productID or CategoryID
     */
    public function getProductsFeed() {
        $category_id = $this->getRequest()->getQuery('category_id');
        $product_id = $this->getRequest()->getQuery('product_id');

        if (empty($category_id) && empty($product_id)) {
            echo 'Nothing to fetch. Missing product_id or category_id';
            exit;
        }

        //If category is set then get the products from that category
        //If category is not set, but product_id is, then get that product
        if (!empty($category_id)) {
            $products = Mage::getModel('catalog/category')->load($category_id);
            $productslist = $products->getProductCollection()->addAttributeToSelect('*')->addAttributeToFilter('status', 1)->addAttributeToFilter('visibility', 4);
            $json = array();
            $prodIds = $productslist->getAllIds();

            foreach ($prodIds as $productId) {
                $product = Mage::getModel('catalog/product')->load($productId);
                $maincat = $subcats = '';
                $cats = $product->getCategoryIds();
                //$eee = implode(",",$cats);
                foreach ($cats as $category_id) {
                    $_cat = Mage::getModel('catalog/category')->load($category_id);
                    if ($subcats == '') {
                        $maincat = $subcats = $_cat->getName();
                    } else {
                        $subcats .= ">" . $_cat->getName();
                    }
                }

                $product_data = array();
                $product_data['ProductID'] = $productId;
                $product_data['ProductName'] = $product->getName();
                $product_data['SKUnumber'] = $product->getSku();
                $product_data['PrimaryCategory'] = $maincat;
                $product_data['SecondaryCategory'] = $subcats;
                $product_data['ProductURL'] = $product->getProductUrl();
                $product_data['ProductImageURL'] = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product' . $product->getImage();
                $product_data['ShortProductDescription'] = substr(iconv("UTF-8", "UTF-8//IGNORE", $product->getDescription()), 0, 150) . "...";
                $product_data['LongProductDescription'] = substr(iconv("UTF-8", "UTF-8//IGNORE", $product->getDescription()), 0, 2000);
                $product_data['SalePrice'] = round($product->getFinalPrice(), 4);
                $product_data['RetailPrice'] = round($product->getPrice(), 4);
                $product_data['UniversalProductCode'] = $product->getData('upc'); //need variable
                $product_data['Currency'] = Mage::app()->getStore()->getCurrentCurrencyCode();

                foreach ($product->getOptions() as $value) {
                    if (is_object($value)) {
                        $values = $value->getValues();
                        foreach ($values as $values) {
                            $product_data['Options']['CustomOptions'][$value->getTitle()][] = $values->getData();
                        }
                    }
                }

                $attributes = $product->getAttributes();
                foreach ($attributes as $attribute) {
                    if ($attribute->getIsVisibleOnFront()) {
                        $product_data['Attributes'][$attribute->getAttributeCode()] = array('label' => $attribute->getFrontend()->getLabel($product), 'value' => $attribute->getFrontend()->getValue($product));
                    }
                }

                if ($product->isConfigurable()) {
                    $productAttributeOptions = $product->getTypeInstance(true)->getConfigurableAttributesAsArray($product);
                    foreach ($productAttributeOptions as $productAttribute) {
                        foreach ($productAttribute['values'] as $attribute) {
                            $product_data['Options']['GlobalOptions'][$productAttribute['label']][$attribute['value_index']] = array('Label' => $attribute['store_label'], 'Pricing' => $attribute['pricing_value']);
                        }
                    }
                }
                $json[] = $product_data;
            }
        } elseif (!empty($product_id)) {
            $json = array();
            $product = Mage::getModel('catalog/product')->load($product_id);
            $maincat = $subcats = '';
            $cats = $product->getCategoryIds();
            //$eee = implode(",",$cats);
            foreach ($cats as $category_id) {
                $_cat = Mage::getModel('catalog/category')->load($category_id);
                if ($subcats == '') {
                    $maincat = $subcats = $_cat->getName();
                } else {
                    $subcats .= ">" . $_cat->getName();
                }
            }

            $product_data = array();
            $product_data['ProductID'] = $product_id;
            $product_data['ProductName'] = $product->getName();
            $product_data['SKUnumber'] = $product->getSku();
            $product_data['PrimaryCategory'] = $maincat;
            $product_data['SecondaryCategory'] = $subcats;
            $product_data['ProductURL'] = $product->getProductUrl();
            $product_data['ProductImageURL'] = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product' . $product->getImage();
            $product_data['ShortProductDescription'] = substr(iconv("UTF-8", "UTF-8//IGNORE", $product->getDescription()), 0, 150) . "...";
            $product_data['LongProductDescription'] = substr(iconv("UTF-8", "UTF-8//IGNORE", $product->getDescription()), 0, 2000);
            $product_data['SalePrice'] = round($product->getFinalPrice(), 4);
            $product_data['RetailPrice'] = round($product->getPrice(), 4);
            $product_data['UniversalProductCode'] = $product->getData('upc'); //need variable
            $product_data['Currency'] = Mage::app()->getStore()->getCurrentCurrencyCode();

            foreach ($product->getOptions() as $value) {
                if (is_object($value)) {
                    $values = $value->getValues();
                    foreach ($values as $values) {
                        $product_data['Options']['CustomOptions'][$value->getTitle()][] = $values->getData();
                    }
                }
            }

            $attributes = $product->getAttributes();
            foreach ($attributes as $attribute) {
                if ($attribute->getIsVisibleOnFront()) {
                    $product_data['Attributes'][$attribute->getAttributeCode()] = array('label' => $attribute->getFrontend()->getLabel($product), 'value' => $attribute->getFrontend()->getValue($product));
                }
            }

            if ($product->isConfigurable()) {
                $productAttributeOptions = $product->getTypeInstance(true)->getConfigurableAttributesAsArray($product);
                foreach ($productAttributeOptions as $productAttribute) {
                    foreach ($productAttribute['values'] as $attribute) {
                        $product_data['Options']['GlobalOptions'][$productAttribute['label']][$attribute['value_index']] = array('Label' => $attribute['store_label'], 'Pricing' => $attribute['pricing_value']);
                    }
                }
            }
            $json[] = $product_data;
        }
        return json_encode($json);
    }

    /*
     * 	Function that generates a JSON Categories feed.
     */

    public function getCategoriesFeed() {
        $recursionLevel = 10;
        $parent = Mage::app()->getStore()->getRootCategoryId();
        $tree = Mage::getResourceModel('catalog/category_tree');
        $nodes = $tree->loadNode($parent)->loadChildren($recursionLevel)->getChildren();
        $tree->addCollectionData(null, false, $parent);
        $categoryTreeData = new stdClass();
        $categoryTreeData->categories = array();
        foreach ($nodes as $node) {
            $categoryTreeData->categories[] = $this->getNodeChildrenData($node);
        }
        return json_encode($categoryTreeData);
    }

    function getNodeChildrenData(Varien_Data_Tree_Node $node) {
        $data = array(
            'title' => $node->getData('name'),
            'id' => $node->getData('entity_id')
        );

        foreach ($node->getChildren() as $childNode) {
            if (!array_key_exists('children', $data)) {
                $data['children'] = array();
            }
            $data['children'][] = $this->getNodeChildrenData($childNode);
        }
        return $data;
    }

    /*
     * 	Function that generates a JSON Stock feed based on productID(s).
     */

    public function getStockFeed() {
        $product_id = $this->getRequest()->getQuery('product_id');
        $stock = array();
        if (empty($product_id)) {
            echo 'Nothing to fetch. Missing product_id.';
            exit;
        }

        $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product_id);
        $stockqty = new stdclass();
        $stockqty->ProductID = $product_id;
        $stockqty->Stock = $stockItem->getQty();
        $stock[] = $stockqty;
        return json_encode($stock);
    }

    /*
     * 	Function that generates a JSON Tax feed.
     */
    public function getTaxFeed() {
        $taxRules = Mage::getModel('tax/sales_order_tax')->getCollection();
        $taxes = array();

        foreach ($taxRules as $taxRule) {
            print_r($taxRule);
            exit;

            $tax_rule = new stdclass();
            $tax_rule->id = $taxRule->getTaxId();
            $tax_rule->name = $taxRule->getTitle();
            $tax_rule->rate = $taxRule->getPercent();
            $taxes[] = $tax_rule;
        }
        return json_encode($taxes);
    }

    /*
     * 	Function that generates a JSON Shipping feed.
     */
    public function getShippingFeed() {
        return 'Shipping feed in json needs to be returned';
    }

    /*
     * This function will generate the product feed, used for FastCheckout shopping
     *
     */
    public function feedAction() {
        $storeId = Mage::app()->getStore()->getStoreId();
        $config = Mage::getStoreConfig('mspcheckout' . "/settings", $storeId);
        $api_key = $this->getRequest()->getQuery('api_key');
        $config_api_key = $config["api_key"];

        if (strtoupper($api_key) == strtoupper($config_api_key)) {
            $keys_match = true;
        } else {
            $keys_match = false;
        }

        //Check if feed is enabled and api keys match
        if ($config["allow_fcofeed"] && $keys_match) {
            $identifier = $this->getRequest()->getQuery('identifier');
            if (empty($identifier)) {
                echo 'Identifier not set';
                exit;
            }
            $json = '';

            switch ($identifier) {
                case "products":
                    $json = $this->getProductsFeed();
                    break;
                case "categories":
                    $json = $this->getCategoriesFeed();
                    break;
                case "stock":
                    $json = $this->getStockFeed();
                    break;
                case "tax":
                    $json = $this->getTaxFeed();
                    break;
                case "shipping":
                    $json = $this->getShippingFeed();
                    break;
            }

            $this->getResponse()->setHeader('Content-type', 'application/json', true);
            echo $json;
            exit;
        } else {
            echo Mage::helper("msp")->__("You are not allowed to request the product feed!");
            exit;
        }
    }

    function json_readable_encode($in, $indent = 0, Closure $_escape = null) {
        if (__CLASS__ && isset($this)) {
            $_myself = array($this, __FUNCTION__);
        } elseif (__CLASS__) {
            $_myself = array('self', __FUNCTION__);
        } else {
            $_myself = __FUNCTION__;
        }

        if (is_null($_escape)) {
            $_escape = function ($str) {
                return str_replace(
                        array('\\', '"', "\n", "\r", "\b", "\f", "\t", '/', '\\\\u'), array('\\\\', '\\"', "\\n", "\\r", "\\b", "\\f", "\\t", '\\/', '\\u'), $str);
            };
        }

        $out = '';

        foreach ($in as $key => $value) {
            $out .= str_repeat("\t", $indent + 1);
            $out .= "\"" . $_escape((string) $key) . "\": ";

            if (is_object($value) || is_array($value)) {
                $out .= "\n";
                $out .= call_user_func($_myself, $value, $indent + 1, $_escape);
            } elseif (is_bool($value)) {
                $out .= $value ? 'true' : 'false';
            } elseif (is_null($value)) {
                $out .= 'null';
            } elseif (is_string($value)) {
                $out .= "\"" . $_escape($value) . "\"";
            } else {
                $out .= $value;
            }

            $out .= ",\n";
        }

        if (!empty($out)) {
            $out = substr($out, 0, -2);
        }

        $out = str_repeat("\t", $indent) . "{\n" . $out;
        $out .= "\n" . str_repeat("\t", $indent) . "}";

        return $out;
    }

}