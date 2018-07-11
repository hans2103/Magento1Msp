<?php

/**
 *
 * @category MultiSafepay
 * @package  MultiSafepay_Msp
 */
require_once(Mage::getBaseDir('lib') . DS . 'multisafepay' . DS . 'MultiSafepay.combined.php');

class MultiSafepay_Msp_StandardController extends Mage_Core_Controller_Front_Action
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
    public function redirectAction()
    {
        $paymentModel = Mage::getSingleton("msp/" . $this->getGatewayModel());
        $selected_gateway = '';
        if (isset($paymentModel->_gateway)) {
            $selected_gateway = $paymentModel->_gateway;
        }

        $paymentModel->setParams($this->getRequest()->getParams());

        if ( !in_array ($selected_gateway, array ('PAYAFTER', 'KLARNA', 'EINVOICE', 'AFTERPAY'))) {
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
    public function returnAction()
    {
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
                $quote->getPayment()->getMethod() == 'msp_klarna'   ||
                $quote->getPayment()->getMethod() == 'msp_afterpay'
            ) {

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
    public function isFCONotification($transId)
    {
        /** @var $quote Mage_Sales_Model_Quote */
        $quote = Mage::getModel('sales/quote')->load($transId);

        $storeId = Mage::app()->getStore()->getStoreId();
        if ($quote) {
            $storeId = $quote->getStoreId();
        }

        $config = Mage::getStoreConfig('mspcheckout/settings', $storeId);

        if (isset($config['account_id']) && isset($config['test_api']) && isset($config['site_id']) && isset($config['secure_code'])) {
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
    public function notificationAction($return = false)
    {
        if (isset($headers['Content-Type']) && $headers['Content-Type'] == 'application/json') {
            echo 'JSON data received on wrong endpoint';
            exit;
        }
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
     * 	Function that generates a Json total products response
     */

    public function getTotalProductsFeed()
    {
        $store = Mage::app()->getStore();
        $store_id = $store->getId();
        $collection = Mage::getResourceModel('catalog/product_collection')->addAttributeToFilter('status', 1)->addAttributeToFilter('visibility', 4);
        $collection->addStoreFilter($store_id);
        $size = $collection->count(); // or $collection->getSize()
        $return = array("total" => $size);
        return json_encode($return);
    }

    /*
     * 	Function that generates a JSON product feed based on productID or CategoryID
     */

    public function getProductsFeed()
    {
        $product_id = $this->getRequest()->getQuery('product_id');
        $stores = array();
        $storeCollection = Mage::getModel('core/store')->getCollection();

        if (empty($product_id)) {

            $offset = $this->getRequest()->getQuery('offset');

            if ($offset == null) {
                echo '{
                        "success": false,
                        "data": {
                            "error_code": "QW-4003",
                            "error": "Offset not set."
                            }
                        }'
                ;
                exit;
            }

            $limit = $this->getRequest()->getQuery('limit');

            if ($limit == null) {
                echo '{
                        "success": false,
                        "data": {
                            "error_code": "QW-4004",
                            "error": "Limit not set."
                            }
                        }'
                ;
                exit;
            }
            //Magento uses pages and not offset amount. We calculate the page based on the limit and offset provided
            if ($offset == 0) {
                $page = 1;
            } else {
                $page = ($offset / $limit) + 1;
            }

            $store = Mage::app()->getStore();
            $store_id = $store->getId();
            $productslist = Mage::getResourceModel('catalog/product_collection')->addAttributeToFilter('status', 1)->addAttributeToFilter('visibility', 4)->setPage($page, $limit)->addStoreFilter($store_id);
            $json = array();

            foreach ($productslist as $theproduct) {
                $productId = $theproduct->getId();
                $product = Mage::getModel('catalog/product')->load($productId);
                $maincat = $subcats = '';
                $cats = $product->getCategoryIds();
                $category_ids = array();

                foreach ($cats as $category_id) {
                    $_cat = Mage::getModel('catalog/category')->load($category_id);
                    if ($_cat->getIsActive()) {
                        $category_ids[] = $category_id;
                    }
                }

                $product_data = array();
                $product_data['product_id'] = $productId;
                $parentIds = null;
                if ($product->getTypeId() == "simple") {
                    $parentIds = Mage::getModel('catalog/product_type_grouped')->getParentIdsByChild($product->getId()); // check for grouped product
                    if (!$parentIds)
                        $parentIds = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($product->getId()); //check for config product
                }

                /* if (!empty($parentIds)) {
                  $product_data['parent_product_id'] = $parentIds[0];
                  } else {
                  $product_data['parent_product_id'] = null;
                  } */
                $product_data['product_name'] = $product->getName();
                $product_data['sku_number'] = $product->getSku();
                $product_data['created'] = date("Y-m-d H:i:s", Mage::getModel("core/date")->timestamp($product->getCreatedAt()));
                $product_data['updated'] = date("Y-m-d H:i:s", Mage::getModel("core/date")->timestamp($product->getUpdatedAt()));

                if ($product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL) {
                    $product_data['downloadable'] = true;
                } else {
                    $product_data['downloadable'] = false;
                }

                if ($product->getGtin()) {
                    $product_data['gtin'] = $product->getGtin();
                    $product_data['unique_identifier'] = true;
                } else {
                    $product_data['gtin'] = null;
                    $product_data['unique_identifier'] = false;
                }

                $product_data['mpn'] = $product->getMpn();
                $product_data['brand'] = $product->getBrand();
                $product_data['weight'] = $product->getWeight();
                $product_data['weight_unit'] = 'kg';
                if ($maincat) {
                    $product_data['primary_category'] = $maincat;
                }
                $product_data['category_ids'] = $category_ids;
                $product_data['product_url'] = $product->getProductUrl();
                $product_data['product_image_urls'] = array();

                if ($product->getImage()) {
                    $mainimage = new stdclass();
                    $mainimage->url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product' . $product->getImage();
                    $mainimage->main = true;
                    $product_data['product_image_urls'][] = $mainimage;
                }

                foreach ($product->getMediaGalleryImages() as $image) {
                    $subimage = new stdclass();
                    $subimage->url = $image->getUrl();
                    $subimage->main = false;
                    $product_data['product_image_urls'][] = $subimage;
                }

                //$product_data['ProductImageURL'] = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product' . $product->getImage();
                foreach ($storeCollection as $store) {
                    $store_id = $store->getId();
                    $language = Mage::getStoreConfig('general/locale/code', $store->getId());

                    $productdata = Mage::getModel('catalog/product')->setStoreId($store_id)->load($productId);
                    $product_data['short_product_description'][$language] = (substr(iconv("UTF-8", "UTF-8//IGNORE", $productdata->getShortDescription()), 0)) ? substr(iconv("UTF-8", "UTF-8//IGNORE", $productdata->getShortDescription()), 0) : "No short description available";
                    $product_data['long_product_description'][$language] = (substr(iconv("UTF-8", "UTF-8//IGNORE", $productdata->getDescription()), 0)) ? substr(iconv("UTF-8", "UTF-8//IGNORE", $productdata->getDescription()), 0) : "No description available";
                }
                $product_data['sale_price'] = number_format((float) $product->getFinalPrice(), 2, '.', '');
                $product_data['retail_price'] = number_format((float) $product->getPrice(), 2, '.', '');

                if ($product->getMspCashback()) {
                    $product_data['cashback'] = $product->getMspCashback();
                }
                //$product_data['UniversalProductCode'] = $product->getData('upc'); //need variable
                /**
                 * Get product tax rule
                 * */
                $taxRules = Mage::getModel('tax/sales_order_tax')->getCollection();
                $taxCalculation = Mage::getModel('tax/calculation');
                $request = $taxCalculation->getRateRequest(null, null, null, $store);
                $tax_rule = new stdclass();
                $rules = array();

                $collection = Mage::getModel('tax/calculation_rule')->getCollection();
                if ($collection->getSize()) {
                    $collection->addCustomerTaxClassesToResult()->addProductTaxClassesToResult()->addRatesToResult();
                }
                if ($collection->getSize()) {
                    foreach ($collection as $rule) {
                        $rule_data = $rule->getData();
                        if (in_array($product->getTaxClassId(), $rule_data['product_tax_classes'])) {
                            foreach ($rule_data['tax_rates'] as $key => $rate_id) {
                                $rate = Mage::getSingleton('tax/calculation_rate')->load($rate_id);
                                $rate_info = $rate->getData();
                                $rules[$rate_info['tax_country_id']] = $rate_info['rate'];
                                $tax_rule->name = $rule_data['code'];
                            }
                        }
                    }
                };

                $tax_rule->id = $product->getTaxClassId();
                $tax_rule->rules = $rules;
                $product_data['tax'] = $tax_rule;
                $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($productId);
                $product_data['stock'] = (INT) $stockItem->getQty();

                $meta_data = array();
                foreach ($storeCollection as $store) {
                    $store_id = $store->getId();
                    $productdata = Mage::getModel('catalog/product')->setStoreId($store_id)->load($productId);
                    $language = Mage::getStoreConfig('general/locale/code', $store->getId());
                    if ($productdata->getMetaTitle() && $productdata->getMetaKeyword() && $productdata->getMetaDescription()) {
                        $meta_data['title'][$language] = $productdata->getMetaTitle();
                        $meta_data['keyword'][$language] = $productdata->getMetaKeyword();
                        $meta_data['description'][$language] = $productdata->getMetaDescription();
                    }
                }

                if (!empty($meta_data)) {
                    $product_data['metadata'] = $meta_data;
                }

                $attr = array();
                $attributes = $product->getAttributes();
                foreach ($storeCollection as $store) {
                    $store_id = $store->getId();
                    Mage::app()->setCurrentStore($store_id);
                    $language = Mage::getStoreConfig('general/locale/code', $store_id);

                    foreach ($attributes as $attribute) {
                        if ($attribute->getIsVisibleOnFront()) {
                            $_condition = $product->getAttributeText($attribute->getAttributeCode());
                            $_coditionDefault = $product->getResource()->getAttribute($attribute->getAttributeCode())->setStoreId($store_id)->getFrontend()->getValue($product);
                            $attribute = Mage::getModel('eav/entity_attribute')->load($attribute->getAttributeId());
                            $langlabels = $attribute->getStoreLabels();

                            if (isset($langlabels[$store_id]) && $_coditionDefault != NULL) {
                                $attr[$attribute->getAttributeCode()][$language] = array('label' => $langlabels[$store_id], 'value' => $_coditionDefault);
                            } elseif ($_coditionDefault) {
                                $attr[$attribute->getAttributeCode()][$language] = array('label' => $attribute->getFrontendLabel(), 'value' => $_coditionDefault);
                            }
                        }
                    }
                }

                if (!empty($attr)) {
                    $product_data['attributes'] = $attr;
                }
                if ($product->isConfigurable()) {
                    $variants = array();
                    /*
                     * GET product variant (options) and add them as variants
                     */
                    $collection = Mage::getModel('catalog/product_type_configurable')->getUsedProducts(null, $product);
                    $childIds = Mage::getModel('catalog/product_type_configurable')->getChildrenIds($product->getId());
                    if ($collection) {
                        $processed = array();
                        foreach ($collection as $childproduct) {
                            if (!in_array($childproduct->getId(), $processed)) {

                                $product_child = Mage::getModel('catalog/product')->load($childproduct->getId());
                                $variant = new stdclass();

                                $variant->product_id = $childproduct->getId();
                                $processed[] = $childproduct->getId();
                                $variant->sku_number = $childproduct->getSku();
                                if ($childproduct->getGtin()) {
                                    $variant->gtin = $childproduct->getGtin();
                                    $variant->unique_identifier = true;
                                } else {
                                    $variant->gtin = null;
                                    $variant->unique_identifier = false;
                                }
                                $product_data['mpn'] = $childproduct->getMpn();
                                if ($childproduct->getImage()) {
                                    $mainimage = new stdclass();
                                    $mainimage->url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product' . $childproduct->getImage();
                                    $mainimage->main = true;
                                    $variant->product_image_urls = array();
                                    $variant->product_image_urls[] = $mainimage;
                                }
                                $childimages = $childproduct->getMediaGalleryImages();
                                if (!empty($childimages)) {
                                    foreach ($childproduct->getMediaGalleryImages() as $image) {
                                        $subimage = new stdclass();
                                        $subimage->url = $image->getUrl();
                                        $subimage->main = false;
                                        $variant->product_image_urls[] = $subimage;
                                    }
                                }

                                $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($childproduct->getId());
                                $variant->stock = (INT) $stockItem->getQty();
                                $variant->sale_price = number_format((float) $product->getFinalPrice(), 2, '.', '');
                                $variant->retail_price = number_format((float) $product->getPrice(), 2, '.', '');
                                $variant->weight = $product_child->getWeight();
                                $variant->weight_unit = 'kg';

                                if ($product_child->getMspCashback()) {
                                    $variant->cashback = $product_child->getMspCashback();
                                }

                                $attrchild = array();
                                $attributes = $childproduct->getAttributes();
                                //print_r($attributes);exit;
                                foreach ($storeCollection as $store) {
                                    $store_id = $store->getId();
                                    Mage::app()->setCurrentStore($store_id);
                                    $language = Mage::getStoreConfig('general/locale/code', $store_id);
                                    foreach ($attributes as $attribute) {
                                        if ($attribute->getIsVisibleOnFront()) {
                                            $_coditionDefault = $childproduct->getResource()->getAttribute($attribute->getAttributeCode())->setStoreId($store_id)->getFrontend()->getValue($childproduct);
                                            $langlabels = $attribute->getStoreLabels();
                                            if (isset($langlabels[$store_id]) && $_coditionDefault != NULL) {
                                                $attrchild[$attribute->getAttributeCode()][$language] = array('label' => $langlabels[$store_id], 'value' => $_coditionDefault);
                                            } elseif ($_coditionDefault) {
                                                $attrchild[$attribute->getAttributeCode()][$language] = array('label' => $attribute->getFrontendLabel(), 'value' => $_coditionDefault);
                                            }
                                        }
                                    }
                                }
                                if (!empty($attrchild)) {
                                    $variant->attributes = $attrchild;
                                }
                                $variants[] = $variant;
                            }
                        }
                    } else {
                        $processed = array();

                        foreach ($childIds[0] as $key => $childid) {
                            $childproduct = Mage::getModel('catalog/product')->load($childid);

                            if (!in_array($childproduct->getId(), $processed)) {
                                $variant = new stdclass();
                                $variant->product_id = $childproduct->getId();
                                $processed[] = $childproduct->getId();
                                $variant->sku_number = $childproduct->getSku();
                                if ($childproduct->getGtin()) {
                                    $variant->gtin = $childproduct->getGtin();
                                    $variant->unique_identifier = true;
                                } else {
                                    $variant->gtin = null;
                                    $variant->unique_identifier = false;
                                }
                                $product_data['mpn'] = $childproduct->getMpn();

                                if ($childproduct->getImage() && $childproduct->getImage() != 'no_selection') {
                                    $mainimage = new stdclass();
                                    $mainimage->url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product' . $childproduct->getImage();
                                    $mainimage->main = true;
                                    $variant->product_image_urls = array();
                                    $variant->product_image_urls[] = $mainimage;
                                }
                                $childimages = $childproduct->getMediaGalleryImages();
                                if (!empty($childimages)) {
                                    foreach ($childproduct->getMediaGalleryImages() as $image) {
                                        $subimage = new stdclass();
                                        $subimage->url = $image->getUrl();
                                        $subimage->main = false;
                                        $variant->product_image_urls[] = $subimage;
                                    }
                                }

                                $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($childproduct->getId());
                                $variant->stock = (INT) $stockItem->getQty();
                                $variant->sale_price = number_format((float) $product->getFinalPrice(), 2, '.', '');
                                $variant->retail_price = number_format((float) $product->getPrice(), 2, '.', '');
                                $variant->weight = $childproduct->getWeight();
                                $variant->weight_unit = 'kg';

                                if ($childproduct->getMspCashback()) {
                                    $variant->cashback = $childproduct->getMspCashback();
                                }

                                $attrchild = array();
                                $attributes = $childproduct->getAttributes();
                                //print_r($attributes);exit;
                                foreach ($storeCollection as $store) {
                                    $store_id = $store->getId();
                                    Mage::app()->setCurrentStore($store_id);
                                    $language = Mage::getStoreConfig('general/locale/code', $store_id);
                                    foreach ($attributes as $attribute) {
                                        if ($attribute->getIsVisibleOnFront()) {
                                            $_coditionDefault = $childproduct->getResource()->getAttribute($attribute->getAttributeCode())->setStoreId($store_id)->getFrontend()->getValue($childproduct);
                                            $langlabels = $attribute->getStoreLabels();
                                            if (isset($langlabels[$store_id]) && $_coditionDefault != NULL) {
                                                $attrchild[$attribute->getAttributeCode()][$language] = array('label' => $langlabels[$store_id], 'value' => $_coditionDefault);
                                            } elseif ($_coditionDefault) {
                                                $attrchild[$attribute->getAttributeCode()][$language] = array('label' => $attribute->getFrontendLabel(), 'value' => $_coditionDefault);
                                            }
                                        }
                                    }
                                }
                                if (!empty($attrchild)) {
                                    $variant->attributes = $attrchild;
                                }
                                $variants[] = $variant;
                            }
                        }
                    }
                    $product_data['variants'] = $variants;
                } elseif ($product->getTypeId() == "grouped") {
                    $variants = array();
                    /*
                     * GET product variant (options) and add them as variants
                     */

                    $collection = Mage::getModel('catalog/product_type_grouped')->getAssociatedProductCollection($product);

                    $processed = array();
                    $prices = array();
                    foreach ($collection as $childproduct) {

                        if (!in_array($childproduct->getId(), $processed)) {

                            $product_child = Mage::getModel('catalog/product')->load($childproduct->getId());

                            $variant = new stdclass();
                            $variant->product_id = $product_child->getId();
                            $processed[] = $product_child->getId();
                            $variant->sku_number = $product_child->getSku();
                            if ($product_child->getGtin()) {
                                $variant->gtin = $product_child->getGtin();
                                $variant->unique_identifier = true;
                            } else {
                                $variant->gtin = null;
                                $variant->unique_identifier = false;
                            }
                            $product_data['mpn'] = $product_child->getMpn();

                            if ($product_child->getImage() && $product_child->getImage() != 'no_selection') {
                                $mainimage = new stdclass();
                                $mainimage->url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product' . $product_child->getImage();
                                $mainimage->main = true;
                                $variant->product_image_urls = array();
                                $variant->product_image_urls[] = $mainimage;
                            }

                            $childimages = $product_child->getMediaGalleryImages();
                            if (!empty($childimages)) {
                                foreach ($product_child->getMediaGalleryImages() as $image) {
                                    $subimage = new stdclass();
                                    $subimage->url = $image->getUrl();
                                    $subimage->main = false;
                                    $variant->product_image_urls[] = $subimage;
                                }
                            }
                            $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product_child->getId());
                            $variant->stock = (INT) $stockItem->getQty();
                            $variant->sale_price = number_format((float) $product_child->getFinalPrice(), 2, '.', '');
                            $variant->retail_price = number_format((float) $product_child->getPrice(), 2, '.', '');
                            $variant->weight = $product_child->getWeight();
                            $variant->weight_unit = 'kg';
                            if ($product_child->getMspCashback()) {
                                $variant->cashback = $product_child->getMspCashback();
                            }



                            $prices[] = $variant->sale_price;

                            $attrchild = array();
                            $attributes = $product_child->getAttributes();
                            //print_r($attributes);exit;
                            foreach ($storeCollection as $store) {
                                $store_id = $store->getId();
                                Mage::app()->setCurrentStore($store_id);
                                $language = Mage::getStoreConfig('general/locale/code', $store_id);
                                foreach ($attributes as $attribute) {
                                    if ($attribute->getIsVisibleOnFront()) {
                                        $_coditionDefault = $product_child->getResource()->getAttribute($attribute->getAttributeCode())->setStoreId($store_id)->getFrontend()->getValue($product_child);
                                        $langlabels = $attribute->getStoreLabels();
                                        if (isset($langlabels[$store_id]) && $_coditionDefault != NULL) {
                                            $attrchild[$attribute->getAttributeCode()][$language] = array('label' => $langlabels[$store_id], 'value' => $_coditionDefault);
                                        } elseif ($_coditionDefault) {
                                            $attrchild[$attribute->getAttributeCode()][$language] = array('label' => $attribute->getFrontendLabel(), 'value' => $_coditionDefault);
                                        }
                                    }
                                }
                            }
                            if (!empty($attrchild)) {
                                $variant->attributes = $attrchild;
                            }
                            $variants[] = $variant;
                        }
                    }

                    /**
                     * Get child product tax rule. We need to set this as the main product is of type grouped and does not have this value.
                     * */
                    $taxRules = Mage::getModel('tax/sales_order_tax')->getCollection();
                    $taxCalculation = Mage::getModel('tax/calculation');
                    $request = $taxCalculation->getRateRequest(null, null, null, $store);
                    $tax_rule = new stdclass();
                    $rules = array();

                    $collection = Mage::getModel('tax/calculation_rule')->getCollection();
                    if ($collection->getSize()) {
                        $collection->addCustomerTaxClassesToResult()->addProductTaxClassesToResult()->addRatesToResult();
                    }
                    if ($collection->getSize()) {
                        foreach ($collection as $rule) {
                            $rule_data = $rule->getData();
                            if (in_array($product_child->getTaxClassId(), $rule_data['product_tax_classes'])) {
                                foreach ($rule_data['tax_rates'] as $key => $rate_id) {
                                    $rate = Mage::getSingleton('tax/calculation_rate')->load($rate_id);
                                    $rate_info = $rate->getData();
                                    $rules[$rate_info['tax_country_id']] = $rate_info['rate'];
                                    $tax_rule->name = $rule_data['code'];
                                }
                            }
                        }
                    };

                    $tax_rule->id = $product_child->getTaxClassId();
                    $tax_rule->rules = $rules;
                    $product_data['tax'] = $tax_rule;


                    $product_data['from_price'] = min($prices);
                    $product_data['variants'] = $variants;
                }

                $options = $product->getOptions();

                if (!empty($options)) {
                    foreach ($storeCollection as $store) {
                        $store_id = $store->getId();
                        $language = Mage::getStoreConfig('general/locale/code', $store->getId());
                        $productdata = Mage::getModel('catalog/product')->setStoreId($store_id)->load($productId);
                        foreach ($productdata->getOptions() as $value) {
                            if (is_object($value)) {
                                $optionobjects = $value->getValues();
                                $values = array();
                                foreach ($optionobjects as $options) {
                                    $data = $options->getData();
                                    $optiondata = new stdclass();
                                    $optiondata->id = $data['option_type_id'];
                                    $optiondata->label = $data['title'];
                                    $optiondata->pricing = $data['price'];
                                    $optiondata->price_type = $data['price_type'];
                                    $values[] = $optiondata;
                                    if (!empty($data['option_type_id'])) {
                                        $product_data['options']['global_options'][$language][$value->getTitle()] = array(
                                            'id' => $data['option_id'],
                                            'type' => 'custom',
                                            //'label' => $value->getTitle(),
                                            'values' => $values
                                        );
                                    }
                                }
                            }
                        }
                    }
                }
                if ($product->getName() != null && $product->getTypeId() != "bundle" && $product->getTypeId() != "downloadable") {
                    $json[] = $product_data;
                }
            }
        } elseif (!empty($product_id)) {
            $stores = array();
            $storeCollection = Mage::getModel('core/store')->getCollection();

            $json = array();
            $product = Mage::getModel('catalog/product')->load($product_id);

            if ($product->getTypeId() == "bundle" || $product->getTypeId() == "downloadable") {
                echo '{
                        "success": false,
                        "data": {
                            "error_code": "QW-4005",
                            "error": "Product type not supported."
                            }
                        }'
                ;
                exit;
            }

            $maincat = $subcats = '';
            $cats = $product->getCategoryIds();
            $category_ids = array();
            foreach ($cats as $category_id) {
                $_cat = Mage::getModel('catalog/category')->load($category_id);
                if ($_cat->getIsActive()) {
                    $category_ids[] = $category_id;
                }
            }

            $product_data = array();
            $product_data['product_id'] = $product_id;
            $parentIds = null;
            if ($product->getTypeId() == "simple") {
                $parentIds = Mage::getModel('catalog/product_type_grouped')->getParentIdsByChild($product->getId()); // check for grouped product
                if (!$parentIds)
                    $parentIds = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($product->getId()); //check for config product
            }

            /* if (!empty($parentIds)) {
              $product_data['parent_product_id'] = $parentIds[0];
              } else {
              $product_data['parent_product_id'] = null;
              } */
            $product_data['product_name'] = $product->getName();
            $product_data['sku_number'] = $product->getSku();
            $product_data['created'] = date("Y-m-d H:i:s", Mage::getModel("core/date")->timestamp($product->getCreatedAt()));
            $product_data['updated'] = date("Y-m-d H:i:s", Mage::getModel("core/date")->timestamp($product->getUpdatedAt()));

            if ($product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL) {
                $product_data['downloadable'] = true;
            } else {
                $product_data['downloadable'] = false;
            }

            if ($product->getGtin()) {
                $product_data['gtin'] = $product->getGtin();
                $product_data['unique_identifier'] = true;
            } else {
                $product_data['gtin'] = null;
                $product_data['unique_identifier'] = false;
            }

            $product_data['mpn'] = $product->getMpn();
            $product_data['brand'] = $product->getBrand();
            $product_data['weight'] = $product->getWeight();
            $product_data['weight_unit'] = 'kg';
            $product_data['category_ids'] = $category_ids;
            $product_data['product_url'] = $product->getProductUrl();
            $product_data['product_image_urls'] = array();

            if ($product->getImage()) {
                $mainimage = new stdclass();
                $mainimage->url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product' . $product->getImage();
                $mainimage->main = true;
                $product_data['product_image_urls'][] = $mainimage;
            }

            foreach ($product->getMediaGalleryImages() as $image) {
                $subimage = new stdclass();
                $subimage->url = $image->getUrl();
                $subimage->main = false;
                $product_data['product_image_urls'][] = $subimage;
            }

            foreach ($storeCollection as $store) {
                $store_id = $store->getId();
                $language = Mage::getStoreConfig('general/locale/code', $store->getId());
                $productdata = Mage::getModel('catalog/product')->setStoreId($store_id)->load($product_id);
                $product_data['short_product_description'][$language] = (substr(iconv("UTF-8", "UTF-8//IGNORE", $productdata->getShortDescription()), 0)) ? substr(iconv("UTF-8", "UTF-8//IGNORE", $productdata->getShortDescription()), 0) : "No short description available";
                $product_data['long_product_description'][$language] = (substr(iconv("UTF-8", "UTF-8//IGNORE", $productdata->getDescription()), 0)) ? substr(iconv("UTF-8", "UTF-8//IGNORE", $productdata->getDescription()), 0) : "No description available";
            }

            $product_data['sale_price'] = number_format((float) $product->getFinalPrice(), 2, '.', '');
            $product_data['retail_price'] = number_format((float) $product->getPrice(), 2, '.', '');

            if ($product->getMspCashback()) {
                $product_data['cashback'] = $product->getMspCashback();
            }


            //$product_data['UniversalProductCode'] = $product->getData('upc'); //need variable

            /**
             * Get product tax rule
             * */
            $taxRules = Mage::getModel('tax/sales_order_tax')->getCollection();
            $taxCalculation = Mage::getModel('tax/calculation');
            $request = $taxCalculation->getRateRequest(null, null, null, $store);
            $tax_rule = new stdclass();
            $rules = array();

            $collection = Mage::getModel('tax/calculation_rule')->getCollection();
            if ($collection->getSize()) {
                $collection->addCustomerTaxClassesToResult()->addProductTaxClassesToResult()->addRatesToResult();
            }
            if ($collection->getSize()) {
                foreach ($collection as $rule) {
                    $rule_data = $rule->getData();
                    if (in_array($product->getTaxClassId(), $rule_data['product_tax_classes'])) {
                        foreach ($rule_data['tax_rates'] as $key => $rate_id) {
                            $rate = Mage::getSingleton('tax/calculation_rate')->load($rate_id);
                            $rate_info = $rate->getData();
                            $rules[$rate_info['tax_country_id']] = $rate_info['rate'];
                            $tax_rule->name = $rule_data['code'];
                        }
                    }
                }
            };

            $tax_rule->id = $product->getTaxClassId();
            $tax_rule->rules = $rules;
            $product_data['tax'] = $tax_rule;

            $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product_id);
            $product_data['stock'] = (INT) $stockItem->getQty();

            $meta_data = array();
            foreach ($storeCollection as $store) {
                $store_id = $store->getId();
                $productdata = Mage::getModel('catalog/product')->setStoreId($store_id)->load($product_id);
                $language = Mage::getStoreConfig('general/locale/code', $store->getId());
                if ($productdata->getMetaTitle() && $productdata->getMetaKeyword() && $productdata->getMetaDescription()) {
                    $meta_data['title'][$language] = $productdata->getMetaTitle();
                    $meta_data['keyword'][$language] = $productdata->getMetaKeyword();
                    $meta_data['description'][$language] = $productdata->getMetaDescription();
                }
            }

            if (!empty($meta_data)) {
                $product_data['metadata'] = $meta_data;
            }
            $attr = array();
            $attributes = $product->getAttributes();

            foreach ($storeCollection as $store) {
                $store_id = $store->getId();
                Mage::app()->setCurrentStore($store_id);
                $language = Mage::getStoreConfig('general/locale/code', $store_id);
                foreach ($attributes as $attribute) {
                    if ($attribute->getIsVisibleOnFront()) {
                        $_condition = $product->getAttributeText($attribute->getAttributeCode());
                        $_coditionDefault = $product->getResource()->getAttribute($attribute->getAttributeCode())->setStoreId($store_id)->getFrontend()->getValue($product);
                        $attribute = Mage::getModel('eav/entity_attribute')->load($attribute->getAttributeId());
                        $langlabels = $attribute->getStoreLabels();
                        if (isset($langlabels[$store_id]) && $_coditionDefault != NULL) {
                            $attr[$attribute->getAttributeCode()][$language] = array('label' => $langlabels[$store_id], 'value' => $_coditionDefault);
                        } elseif ($_coditionDefault) {
                            $attr[$attribute->getAttributeCode()][$language] = array('label' => $attribute->getFrontendLabel(), 'value' => $_coditionDefault);
                        }
                    }
                }
            }
            if (!empty($attr)) {
                $product_data['attributes'] = $attr;
            }

            if ($product->isConfigurable()) {
                $variants = array();
                /*
                 * GET product variant (options) and add them as variants
                 */
                $collection = Mage::getModel('catalog/product_type_configurable')->getUsedProducts(null, $product);

                $processed = array();

                foreach ($collection as $childproduct) {

                    if (!in_array($childproduct->getId(), $processed)) {

                        $product_child = Mage::getModel('catalog/product')->load($childproduct->getId());


                        $variant = new stdclass();
                        $variant->product_id = $childproduct->getId();
                        $processed[] = $childproduct->getId();
                        $variant->sku_number = $childproduct->getSku();
                        if ($childproduct->getGtin()) {
                            $variant->gtin = $childproduct->getGtin();
                            $variant->unique_identifier = true;
                        } else {
                            $variant->gtin = null;
                            $variant->unique_identifier = false;
                        }
                        $product_data['mpn'] = $childproduct->getMpn();

                        if ($childproduct->getImage() && $childproduct->getImage() != 'no_selection') {
                            $mainimage = new stdclass();
                            $mainimage->url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product' . $childproduct->getImage();
                            $mainimage->main = true;
                            $variant->product_image_urls = array();
                            $variant->product_image_urls[] = $mainimage;
                        }

                        $childimages = $childproduct->getMediaGalleryImages();
                        if (!empty($childimages)) {
                            foreach ($childproduct->getMediaGalleryImages() as $image) {
                                $subimage = new stdclass();
                                $subimage->url = $image->getUrl();
                                $subimage->main = false;
                                $variant->product_image_urls[] = $subimage;
                            }
                        }
                        $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($childproduct->getId());
                        $variant->stock = (INT) $stockItem->getQty();
                        $variant->sale_price = number_format((float) $childproduct->getFinalPrice(), 2, '.', '');
                        $variant->retail_price = number_format((float) $childproduct->getPrice(), 2, '.', '');
                        $variant->weight = $product_child->getWeight();
                        $variant->weight_unit = 'kg';

                        if ($product_child->getMspCashback()) {
                            $variant->cashback = $product_child->getMspCashback();
                        }


                        $attrchild = array();
                        $attributes = $childproduct->getAttributes();
                        //print_r($attributes);exit;
                        foreach ($storeCollection as $store) {
                            $store_id = $store->getId();
                            Mage::app()->setCurrentStore($store_id);
                            $language = Mage::getStoreConfig('general/locale/code', $store_id);
                            foreach ($attributes as $attribute) {
                                if ($attribute->getIsVisibleOnFront()) {
                                    $_coditionDefault = $childproduct->getResource()->getAttribute($attribute->getAttributeCode())->setStoreId($store_id)->getFrontend()->getValue($childproduct);
                                    $langlabels = $attribute->getStoreLabels();
                                    if (isset($langlabels[$store_id]) && $_coditionDefault != NULL) {
                                        $attrchild[$attribute->getAttributeCode()][$language] = array('label' => $langlabels[$store_id], 'value' => $_coditionDefault);
                                    } elseif ($_coditionDefault) {
                                        $attrchild[$attribute->getAttributeCode()][$language] = array('label' => $attribute->getFrontendLabel(), 'value' => $_coditionDefault);
                                    }
                                }
                            }
                        }
                        if (!empty($attrchild)) {
                            $variant->attributes = $attrchild;
                        }
                        $variants[] = $variant;
                    }
                }
                $product_data['variants'] = $variants;
            } elseif ($product->getTypeId() == "grouped") {
                $variants = array();
                /*
                 * GET product variant (options) and add them as variants
                 */

                $collection = Mage::getModel('catalog/product_type_grouped')->getAssociatedProductCollection($product);

                $processed = array();
                $prices = array();
                foreach ($collection as $childproduct) {

                    if (!in_array($childproduct->getId(), $processed)) {

                        $product_child = Mage::getModel('catalog/product')->load($childproduct->getId());

                        $variant = new stdclass();
                        $variant->product_id = $product_child->getId();
                        $processed[] = $product_child->getId();
                        $variant->sku_number = $product_child->getSku();
                        if ($product_child->getGtin()) {
                            $variant->gtin = $product_child->getGtin();
                            $variant->unique_identifier = true;
                        } else {
                            $variant->gtin = null;
                            $variant->unique_identifier = false;
                        }
                        $product_data['mpn'] = $product_child->getMpn();

                        if ($product_child->getImage() && $product_child->getImage() != 'no_selection') {
                            $mainimage = new stdclass();
                            $mainimage->url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product' . $product_child->getImage();
                            $mainimage->main = true;
                            $variant->product_image_urls = array();
                            $variant->product_image_urls[] = $mainimage;
                        }

                        $childimages = $product_child->getMediaGalleryImages();
                        if (!empty($childimages)) {
                            foreach ($product_child->getMediaGalleryImages() as $image) {
                                $subimage = new stdclass();
                                $subimage->url = $image->getUrl();
                                $subimage->main = false;
                                $variant->product_image_urls[] = $subimage;
                            }
                        }
                        $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product_child->getId());
                        $variant->stock = (INT) $stockItem->getQty();
                        $variant->sale_price = number_format((float) $product_child->getFinalPrice(), 2, '.', '');
                        $variant->retail_price = number_format((float) $product_child->getPrice(), 2, '.', '');
                        $variant->weight = $product_child->getWeight();
                        $variant->weight_unit = 'kg';
                        if ($product_child->getMspCashback()) {
                            $variant->cashback = $product_child->getMspCashback();
                        }



                        $prices[] = $variant->sale_price;

                        $attrchild = array();
                        $attributes = $product_child->getAttributes();
                        //print_r($attributes);exit;
                        foreach ($storeCollection as $store) {
                            $store_id = $store->getId();
                            Mage::app()->setCurrentStore($store_id);
                            $language = Mage::getStoreConfig('general/locale/code', $store_id);
                            foreach ($attributes as $attribute) {
                                if ($attribute->getIsVisibleOnFront()) {
                                    $_coditionDefault = $product_child->getResource()->getAttribute($attribute->getAttributeCode())->setStoreId($store_id)->getFrontend()->getValue($product_child);
                                    $langlabels = $attribute->getStoreLabels();
                                    if (isset($langlabels[$store_id]) && $_coditionDefault != NULL) {
                                        $attrchild[$attribute->getAttributeCode()][$language] = array('label' => $langlabels[$store_id], 'value' => $_coditionDefault);
                                    } elseif ($_coditionDefault) {
                                        $attrchild[$attribute->getAttributeCode()][$language] = array('label' => $attribute->getFrontendLabel(), 'value' => $_coditionDefault);
                                    }
                                }
                            }
                        }
                        if (!empty($attrchild)) {
                            $variant->attributes = $attrchild;
                        }
                        $variants[] = $variant;
                    }
                }

                /**
                 * Get child product tax rule. We need to set this as the main product is of type grouped and does not have this value.
                 * */
                $taxRules = Mage::getModel('tax/sales_order_tax')->getCollection();
                $taxCalculation = Mage::getModel('tax/calculation');
                $request = $taxCalculation->getRateRequest(null, null, null, $store);
                $tax_rule = new stdclass();
                $rules = array();

                $collection = Mage::getModel('tax/calculation_rule')->getCollection();
                if ($collection->getSize()) {
                    $collection->addCustomerTaxClassesToResult()->addProductTaxClassesToResult()->addRatesToResult();
                }
                if ($collection->getSize()) {
                    foreach ($collection as $rule) {
                        $rule_data = $rule->getData();
                        if (in_array($product_child->getTaxClassId(), $rule_data['product_tax_classes'])) {
                            foreach ($rule_data['tax_rates'] as $key => $rate_id) {
                                $rate = Mage::getSingleton('tax/calculation_rate')->load($rate_id);
                                $rate_info = $rate->getData();
                                $rules[$rate_info['tax_country_id']] = $rate_info['rate'];
                                $tax_rule->name = $rule_data['code'];
                            }
                        }
                    }
                };

                $tax_rule->id = $product_child->getTaxClassId();
                $tax_rule->rules = $rules;
                $product_data['tax'] = $tax_rule;


                $product_data['from_price'] = min($prices);
                $product_data['variants'] = $variants;
            }


            $options = $product->getOptions();

            if (!empty($options)) {
                foreach ($storeCollection as $store) {
                    $store_id = $store->getId();
                    $language = Mage::getStoreConfig('general/locale/code', $store->getId());
                    $productdata = Mage::getModel('catalog/product')->setStoreId($store_id)->load($product_id);
                    foreach ($productdata->getOptions() as $value) {
                        if (is_object($value)) {
                            $optionobjects = $value->getValues();
                            $values = array();
                            foreach ($optionobjects as $options) {
                                $data = $options->getData();
                                $optiondata = new stdclass();
                                $optiondata->id = $data['option_type_id'];
                                $optiondata->label = $data['title'];
                                $optiondata->pricing = $data['price'];
                                $optiondata->price_type = $data['price_type'];
                                $values[] = $optiondata;

                                if (!empty($data['option_type_id'])) {
                                    $product_data['options']['global_options'][$language][$value->getTitle()] = array(
                                        'id' => $data['option_id'],
                                        'type' => 'custom',
                                        //'label' => $value->getTitle(),
                                        'values' => $values
                                    );
                                }
                            }
                        }
                    }
                }
            }
            if ($product->getName() != null) {
                $json[] = $product_data;
            }
        }
        return json_encode($json);
    }

    function getCategoryTree($recursionLevel, $storeId = 1)
    {
        $parent = 0; //Mage::app()->getStore()->getRootCategoryId();
        $tree = Mage::getResourceModel('catalog/category_tree');
        /* @var $tree Mage_Catalog_Model_Resource_Category_Tree */

        $nodes = $tree->loadNode($parent)->loadChildren($recursionLevel)->getChildren();
        $tree->addCollectionData(null, false, $parent);

        $categoryTreeData = array();
        foreach ($nodes as $node) {
            $categoryTreeData[] = $this->getNodeChildrenData($node);
        }

        return $categoryTreeData;
    }

    function getNodeChildrenData(Varien_Data_Tree_Node $node)
    {
        if (strlen($code = Mage::app()->getRequest()->getParam('store'))) { // store level
            $store_id = Mage::getModel('core/store')->load($code)->getId();
        } elseif (strlen($code = $code = Mage::app()->getRequest()->getParam('website'))) { // website level
            $website_id = Mage::getModel('core/website')->load($code)->getId();
            $store_id = Mage::app()->getWebsite($website_id)->getDefaultStore()->getId();
        } else { // default level
            $store_id = 0;
        }

        $language = Mage::getStoreConfig('general/locale/code', $store_id);
        $data = array();
        $data['id'] = $node->getData('entity_id');
        $data['title'][$language] = $node->getData('name');

        $childCategory = Mage::getModel('catalog/category');
        $childCategory->setStoreId($store_id);
        $childCategory->load($node->getData('entity_id'));
        if ($childCategory->getMspCashback()) {
            $data['cashback'] = $childCategory->getMspCashback();
        }

        foreach ($node->getChildren() as $childNode) {
            if (!array_key_exists('children', $data)) {
                $data['children'] = array();
            }

            $data['children'][] = $this->getNodeChildrenData($childNode);
        }
        return $data;
    }

    /*
     * 	Function that generates a JSON Categories feed.
     */

    public function getCategoriesFeed()
    {
        if (!Mage::helper('catalog/category_flat')->isEnabled()) {
            $categoryTreeData = $this->getCategoryTree(3);
        } else {
            $stores = array();
            $storeCollection = Mage::getModel('core/store')->getCollection();
            $categoryTreeData = array();
            $_helper = Mage::helper('catalog/category');
            $_categories = $_helper->getStoreCategories(false, true, true);

            if (count($_categories) > 0) {
                foreach ($_categories as $_category) {
                    $_category = Mage::getModel('catalog/category')->load($_category->getId());
                    $cattrans = array();
                    foreach ($storeCollection as $store) {
                        $store_id = $store->getId();
                        $language = Mage::getStoreConfig('general/locale/code', $store->getId());
                        $cattrans['id'] = $_category->getId();
                        $cattrans['title'][$language] = $_category->getName();

                        if ($_category->getMspCashback()) {
                            $cattrans['cashback'] = $_category->getMspCashback();
                        }
                    }

                    $_subcategories = $_category->getChildrenCategories();
                    if (count($_subcategories) > 0) {
                        foreach ($_subcategories as $_subcategory) {
                            $children = array();
                            $childs = array();

                            foreach ($storeCollection as $store) {
                                $store_id = $store->getId();
                                $language = Mage::getStoreConfig('general/locale/code', $store->getId());
                                $children['id'] = $_subcategory->getId();
                                $children['title'][$language] = $_subcategory->getName();
                                if ($_subcategory->getMspCashback()) {
                                    $children['cashback'] = $_subcategory->getMspCashback();
                                }
                            }
                            $cattrans['children'][] = $children;
                        }
                    }
                    if (!empty($cattrans)) {
                        $categoryTreeData[] = $cattrans;
                    }
                }
            }
        }
        return json_encode($categoryTreeData);
    }

    /*
     * 	Function that generates a JSON Stock feed based on productID(s).
     */

    public function getStockFeed()
    {
        $product_id = null;
        $variant_id = null;

        if (isset($_GET['product_id'])) {
            $product_id = $this->getRequest()->getQuery('product_id');
        }

        if (isset($_GET['variant_id'])) {
            $variant_id = $this->getRequest()->getQuery('variant_id');
        }

        $stock = '';
        if (empty($product_id) && empty($variant_id)) {
            echo '{
                        "success": false,
                        "data": {
                            "error_code": "QW-4002",
                            "error": "Product ID not set."
                            }
                        }'
            ;

            exit;
        }

        if ($variant_id != null) {
            $product_id = $variant_id;
        }


        $product = Mage::getModel('catalog/product')->load($product_id);
        $validproducts = array();
        if ($product->isConfigurable() && $this->getRequest()->getParam('options')) {
            $remove = array("[", "]");
            $options = $this->getRequest()->getParam('options');
            $dataoptions = str_replace($remove, "", $options);
            $data = explode(',', $dataoptions);

            $attribute_values = array();
            $childs = Mage::getResourceSingleton('catalog/product_type_configurable')->getChildrenIds($product_id);
            $productAttributeOptions = $product->getTypeInstance(true)->getConfigurableAttributesAsArray($product);

            foreach ($productAttributeOptions as $productAttribute) {
                $attribute_code = $productAttribute['attribute_code'];
                foreach ($data as $option) {
                    $attribute_id = $this->strbefore($option, '|');
                    $attribute_value = $this->strafter($option, '|');
                    if ($productAttribute['attribute_id'] == $attribute_id) {
                        $attribute_values[] = $attribute_value;
                    }
                }

                foreach ($attribute_values as $att_val) {
                    foreach ($childs as $child => $value) {
                        foreach ($value as $productid) {
                            $childproduct = Mage::getModel('catalog/product')->load($productid);
                            $data2 = $childproduct->getData();
                            if (isset($data2[$attribute_code]) && $data2[$attribute_code] == $att_val) {
                                //if(!in_array($productid, $validproducts)){
                                //$validproducts[$productid] = $productid;
                                $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($productid);
                                $stockqty = new stdclass();
                                $stockqty->product_id = $product_id;
                                $stockqty->stock = (int) $stockItem->getQty();
                                $stock = $stockqty;
                                //}
                            }
                        }
                    }
                }
            }
        } else {
            $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product_id);
            $stockqty = new stdclass();
            $stockqty->product_id = $product_id;
            $stockqty->stock = (int) $stockItem->getQty();
            $stock = $stockqty;
        }
        return json_encode($stock);
    }

    function strafter($string, $substring)
    {
        $pos = strpos($string, $substring);
        if ($pos === false)
            return $string;
        else
            return(substr($string, $pos + strlen($substring)));
    }

    function strbefore($string, $substring)
    {
        $pos = strpos($string, $substring);
        if ($pos === false)
            return $string;
        else
            return(substr($string, 0, $pos));
    }

    /*
     * 	Function that generates a JSON Tax feed.
     */
    /* public function getTaxFeed() {
      $taxRules = Mage::getModel('tax/sales_order_tax')->getCollection();

      $alternate=  array();
      $inRuleSet = array();
      foreach ($taxRules as $taxRule) {
      if(!in_array($taxRule->getTitle(), $inRuleSet)){
      $tax_rule = new stdclass();
      $tax_rule->id = $taxRule->getTaxId();
      $code = $taxRule->getCode();
      $rate = Mage::getModel('tax/calculation_rate')->loadByCode($code);
      $tax_rule->name = $taxRule->getTitle();
      $rule = array();
      $rule[$rate->getTaxCountryId()]=$taxRule->getPercent();
      $tax_rule->rules = $rule;
      $alternate[] = $tax_rule;

      $inRuleSet[] =  $taxRule->getTitle();
      }
      }
      return json_encode($alternate);
      } */

    /*
     * 	Function that generates a JSON Shipping feed.
     */

    public function getShippingFeed()
    {
        if ($this->getRequest()->getParam('amount') && $this->getRequest()->getParam('items_count')) {
            $specific_request = true;
        } else {
            $specific_request = false;
        }
        $shippingMethods = array();

        //all method
        $carriers = Mage::getStoreConfig('carriers', Mage::app()->getStore()->getId());

        foreach ($carriers as $carrierCode => $carrierConfig) {
            if ($carrierConfig['active']) {
                if ($specific_request == false) {
                    if (isset($carrierConfig['price'])) {
                        $method = new stdclass();
                        $method->id = $carrierCode;
                        $method->type = "flat_rate_shipping";
                        $method->provider = $carrierCode;
                        $method->name = $carrierConfig['name'];
                        $method->price = number_format((float) $carrierConfig['price'], 2, '.', ''); //$carrierConfig['price'];
                        $areas = explode(',', $carrierConfig['specificcountry']);
                        $method->allowed_areas = array();
                        foreach ($areas as $area) {
                            $method->allowed_areas[] = $area;
                        }
                        $shippingMethods[] = $method;
                    }
                } else {
                    $remove = array('[', ']', '"', ' ');
                    $options = urldecode($this->getRequest()->getParam('countries'));
                    $dataoptions = str_replace($remove, "", $options);
                    $countries = explode(',', $dataoptions);

                    /**
                     * Get Shipping tax rule
                     * */
                    $shipping_tax_id = Mage::getStoreConfig('tax/classes/shipping_tax_class', Mage::app()->getStore()->getId());
                    $taxRules = Mage::getModel('tax/sales_order_tax')->getCollection();
                    $taxCalculation = Mage::getModel('tax/calculation');
                    $request = $taxCalculation->getRateRequest(null, null, null, Mage::app()->getStore());
                    $tax_rule = new stdclass();
                    $rules = array();

                    $collection = Mage::getModel('tax/calculation_rule')->getCollection();
                    if ($collection->getSize()) {
                        $collection->addCustomerTaxClassesToResult()->addProductTaxClassesToResult()->addRatesToResult();
                    }
                    if ($collection->getSize()) {
                        foreach ($collection as $rule) {
                            $rule_data = $rule->getData();
                            if (in_array($shipping_tax_id, $rule_data['product_tax_classes'])) {

                                foreach ($rule_data['tax_rates'] as $key => $rate_id) {
                                    $rate = Mage::getSingleton('tax/calculation_rate')->load($rate_id);
                                    $rate_info = $rate->getData();

                                    foreach ($countries as $country) {
                                        if ($country == $rate_info['tax_country_id']) {
                                            $tax_name = $rule_data['code'];
                                            $tax_rate = $rate_info['tax_country_id'] = $rate_info['rate'];
                                        }
                                    }
                                }
                            }
                        }
                    }

                    if (isset($carrierConfig['price']) && isset($carrierConfig['name']) && !empty($carrierConfig['name']) && isset($carrierConfig['model']) && $carrierConfig['model'] != "postnl_carrier/postnl") {
                        $method = new stdclass();
                        $method->id = $carrierCode;
                        $method->type = "flat_rate_shipping";
                        $method->provider = $carrierCode;
                        $method->name = $carrierConfig['name'];
                        $method->tax->name = $tax_name;
                        $method->tax->id = $shipping_tax_id;
                        $method->tax->rate = $tax_rate;

                        $price = 0;
                        if ($carrierConfig['model'] == 'shipping/carrier_flatrate') {
                            if ($carrierConfig['type'] == 'I') {
                                $price = $this->getRequest()->getParam('items_count') * $carrierConfig['price'];
                                $price = number_format((float) $price, 2, '.', ''); //$carrierConfig['price'];
                            } else {
                                $price = number_format((float) $carrierConfig['price'], 2, '.', ''); //$carrierConfig['price'];
                            }
                        } else {
                            $price = $carrierConfig['price'];
                        }

                        $method->price = number_format((float) $price, 2, '.', '');

                        if (!empty($carrierConfig['specificcountry'])) {
                            $areas = explode(',', $carrierConfig['specificcountry']);
                            foreach ($areas as $area) {
                                if (in_array($area, $countries)) {
                                    $shippingMethods[] = $method;
                                }
                            }
                        } else {
                            $shippingMethods[] = $method;
                        }
                    } elseif ('shipping/carrier_freeshipping' == $carrierConfig['model']) {
                        $amount = $this->getRequest()->getParam('amount') / 100;
                        if ($amount >= $carrierConfig['free_shipping_subtotal']) {
                            $method = new stdclass();
                            $method->id = $carrierCode;
                            $method->type = "flat_rate_shipping";
                            $method->provider = $carrierCode;
                            $method->name = $carrierConfig['name'];
                            $method->price = number_format((float) 0, 2, '.', '');
                            $method->tax->name = $tax_name;
                            $method->tax->id = $shipping_tax_id;
                            $method->tax->rate = $tax_rate;

                            if (!empty($carrierConfig['specificcountry'])) {
                                $areas = explode(',', $carrierConfig['specificcountry']);
                                foreach ($areas as $area) {
                                    if (in_array($area, $countries)) {
                                        $shippingMethods[] = $method;
                                    }
                                }
                            } else {
                                $shippingMethods[] = $method;
                            }
                        }
                    } elseif ('oss_ossdeliveryoption/carrier' == $carrierConfig['model']) {
                        $request = Mage::getModel('shipping/rate_request');
                        $request->setPackageWeight($this->getRequest()->getParam('weight'));
                        $request->setDestCountryId($countries[0]);
                        $request->setDestPostcode($this->getRequest()->getParam('zipcode'));
                        $request->setPackageQty($this->getRequest()->getParam('items_count'));
                        $request->setPackageValue($this->getRequest()->getParam('amount'));
                        $ossrates = Mage::getModel('oss_ossdeliveryoption/carrier')->collectRates($request);

                        $rates = $this->getProtectedValue($ossrates, '_rates');
                        foreach ($rates as $rate) {

                            $method = new stdclass();
                            $method->id = $rate->getData('carrier');
                            $method->type = "flat_rate_shipping";
                            $method->provider = $rate->getData('method');
                            $method->name = $rate->getData('method_title');
                            $method->price = number_format((float) $rate->getData('price'), 2, '.', '');
                            $method->tax->name = $tax_name;
                            $method->tax->id = $shipping_tax_id;
                            $method->tax->rate = $tax_rate;

                            if (!empty($carrierConfig['specificcountry'])) {
                                $areas = explode(',', $carrierConfig['specificcountry']);
                                foreach ($areas as $area) {
                                    if (in_array($area, $countries)) {
                                        $shippingMethods[] = $method;
                                    }
                                }
                            } else {
                                $shippingMethods[] = $method;
                            }
                        }
                    } elseif ('postnl_carrier/postnl' == $carrierConfig['model']) {
                        $request = Mage::getModel('shipping/rate_request');
                        $request->setPackageWeight($this->getRequest()->getParam('weight'));
                        $request->setFreeMethodWeight($this->getRequest()->getParam('weight'));
                        $request->setDestCountryId($countries[0]);
                        $request->setDestPostcode($this->getRequest()->getParam('zipcode'));
                        $request->setPackageQty($this->getRequest()->getParam('items_count'));
                        $request->setPackageValue($this->getRequest()->getParam('amount'));
                        $request->setBaseSubtotalInclTax($this->getRequest()->getParam('amount'));
                        $request->setWebsiteId(Mage::app()->getWebsite()->getId());
                        $ossrates = Mage::getModel('postnl_carrier/postnl')->collectRates($request);
                        $rates = $this->getProtectedValue($ossrates, '_rates');
                        foreach ($rates as $rate) {

                            $method = new stdclass();
                            $method->id = $rate->getData('carrier');
                            $method->type = "flat_rate_shipping";
                            $method->provider = $rate->getData('method');
                            $method->name = $rate->getData('method_title');
                            $method->price = number_format((float) $rate->getData('price'), 2, '.', '');
                            $method->tax->name = $tax_name;
                            $method->tax->id = $shipping_tax_id;
                            $method->tax->rate = $tax_rate;

                            if (!empty($carrierConfig['specificcountry'])) {
                                $areas = explode(',', $carrierConfig['specificcountry']);
                                foreach ($areas as $area) {
                                    if (in_array($area, $countries)) {
                                        $shippingMethods[] = $method;
                                    }
                                }
                            } else {
                                $shippingMethods[] = $method;
                            }
                        }
                    } elseif ('pl_store_pickup/carrier_pickup' == $carrierConfig['model']) {
                        $request = Mage::getModel('shipping/rate_request');
                        $request->setPackageWeight($this->getRequest()->getParam('weight'));
                        $request->setDestCountryId($countries[0]);
                        $request->setDestPostcode($this->getRequest()->getParam('zipcode'));
                        $request->setPackageQty($this->getRequest()->getParam('items_count'));
                        $request->setPackageValue($this->getRequest()->getParam('amount'));
                        $pl_pickup = Mage::getModel('pl_store_pickup/carrier_pickup')->collectRates($request);
                        $rates = $this->getProtectedValue($pl_pickup, '_rates');
                        foreach ($rates as $rate) {

                            $method = new stdclass();
                            $method->id = $rate->getData('carrier');
                            $method->type = "flat_rate_shipping";
                            $method->provider = $rate->getData('method');
                            $method->name = $rate->getData('method_title');
                            $method->price = number_format((float) $rate->getData('price'), 2, '.', '');
                            $method->tax->name = $tax_name;
                            $method->tax->id = $shipping_tax_id;
                            $method->tax->rate = $tax_rate;

                            if (!empty($carrierConfig['specificcountry'])) {
                                $areas = explode(',', $carrierConfig['specificcountry']);
                                foreach ($areas as $area) {
                                    if (in_array($area, $countries)) {
                                        $shippingMethods[] = $method;
                                    }
                                }
                            } else {
                                $shippingMethods[] = $method;
                            }
                        }
                    } elseif ('paazl/carrier_paazl' == $carrierConfig['model']) {
                        $request = Mage::getModel('shipping/rate_request');
                        $request->setPackageWeight($this->getRequest()->getParam('weight'));
                        $request->setDestCountryId($countries[0]);
                        $request->setDestPostcode($this->getRequest()->getParam('zipcode'));
                        $request->setPackageQty($this->getRequest()->getParam('items_count'));
                        $request->setPackageValue($this->getRequest()->getParam('amount'));

                        $paazl = Mage::getModel('paazl/carrier_paazl')->collectRates($request);
                        $rates = $this->getProtectedValue($paazl, '_rates');

                        foreach ($rates as $rate) {
                            $method = new stdclass();
                            $method->id = $rate->getData('carrier');
                            $method->type = "flat_rate_shipping";
                            $method->provider = $rate->getData('method');
                            $method->name = $rate->getData('method_title');
                            $method->price = number_format((float) $rate->getData('price'), 2, '.', '');
                            $method->tax->name = $tax_name;
                            $method->tax->id = $shipping_tax_id;
                            $method->tax->rate = $tax_rate;

                            if (!empty($carrierConfig['specificcountry'])) {
                                $areas = explode(',', $carrierConfig['specificcountry']);
                                foreach ($areas as $area) {
                                    if (in_array($area, $countries)) {
                                        $shippingMethods[] = $method;
                                    }
                                }
                            } else {
                                $shippingMethods[] = $method;
                            }
                        }
                    }
                }
            }
        }

        $checkout = Mage::getModel("msp/checkout");
        if ($checkout->getSectionConfigData('checkout_custom_fields/fco_postnl')) {
            $method = new stdclass();
            $method->id = 'postnl';
            $method->name = 'Post NL - Pak je gemak';
            $method->type = "flat_rate_shipping";
            $method->provider = 'postnl';
            $method->tax->name = $tax_name;
            $method->tax->id = $shipping_tax_id;
            $method->tax->rate = $tax_rate;

            //$method->taxid= null;
            $method->price = number_format((float) $checkout->getSectionConfigData('checkout_custom_fields/fco_postnl_amount'), 2, '.', '');

            /* $method->sort_order = $carrierConfig['sort_order'];
              $areas = explode(',', $carrierConfig['specificcountry']);
              $method->allowed_areas = array();
              foreach($areas as $area){
              $method->allowed_areas[] =$area;
              } */
            $shippingMethods[] = $method;
        }


        if ($specific_request == true) {
            $websiteId = Mage::app()->getWebsite()->getId();
            //Table rate based
            $tablerateColl = Mage::getResourceModel('shipping/carrier_tablerate_collection');

            $active = Mage::getStoreConfig('carriers/tablerate/active', Mage::app()->getStore()->getStoreId());
            if ($active) {
                foreach ($tablerateColl as $tablerate) {
                    $table_data = $tablerate->getData();

                    if ($table_data['condition_name'] == 'package_qty') {
                        $items_count = $this->getRequest()->getParam('items_count');
                        if ($items_count >= $table_data['condition_value'] && $websiteId == $table_data['website_id']) {
                            $rate_price = number_format((float) $table_data['price'], 2, '.', '');
                        }
                    } elseif ($table_data['condition_name'] == 'package_value') {
                        $table_data = $tablerate->getData();
                        $remove = array('[', ']', '"', ' ');
                        $options = urldecode($this->getRequest()->getParam('countries'));
                        $dataoptions = str_replace($remove, "", $options);
                        $countries = explode(',', $dataoptions);
                        $country_id = $countries[0];

                        if ($country_id == $table_data['dest_country_id'] && $this->getRequest()->getParam('amount') >= $table_data['condition_value'] && $websiteId == $table_data['website_id']) {
                            $rate_price = number_format((float) $table_data['price'], 2, '.', '');
                        }
                    } else {
                        $item_weight = $this->getRequest()->getParam('weight');

                        if ($item_weight >= $table_data['condition_value'] && $websiteId == $table_data['website_id']) {
                            $rate_price = number_format((float) $table_data['price'], 2, '.', '');
                        }
                    }
                }

                $method = new stdclass();
                $method->id = 'tablerate';
                $method->type = "flat_rate_shipping";
                $method->provider = 'bestway';
                $method->name = Mage::getStoreConfig('carriers/tablerate/title', Mage::app()->getStore()->getId());
                $method->tax->name = $tax_name;
                $method->tax->id = $shipping_tax_id;
                $method->tax->rate = $tax_rate;
                $method->price = $rate_price;
                $ratecountries = Mage::getStoreConfig('carriers/tablerate/specificcountry', Mage::app()->getStore()->getId());
                $ratecountcheck = explode(',', $ratecountries);
                $shippingMethods[] = $method;
                /* if (!empty($ratecountries)) {
                  foreach ($ratecountcheck as $area) {
                  if (in_array($area, $countries)) {
                  $shippingMethods[] = $method;
                  }
                  }
                  } else {
                  $shippingMethods[] = $method;
                  } */
            }
        }
        return json_encode($shippingMethods);
    }

    function getProtectedValue($obj, $name)
    {
        $array = (array) $obj;
        $prefix = chr(0) . '*' . chr(0);
        return $array[$prefix . $name];
    }

    /*
     * 	Function that generates a JSON store info feed.
     */

    public function getStoresFeed()
    {
        $stores = array();
        $languages = array();
        $storeCollection = Mage::getModel('core/store')->getCollection();
        $desc_array = array();
        $CurrencyCodes = Mage::getStoreConfig('currency/options/allow', Mage::app()->getStore()->getId());
        $currencies = explode(',', $CurrencyCodes);
        $allowed_currencies = array();
        foreach ($currencies as $key => $currency) {
            $allowed_currencies[] = $currency;
        }

        $store_data = new stdclass();
        $store = Mage::app()->getStore();
        //foreach ($storeCollection as $store) {
        //$store = Mage::app()->getStore();
        //get languages
        //$languages[] = Mage::getStoreConfig('general/locale/code', $store->getId());
        //get allowed countries
        $allowed = explode(",", Mage::getStoreConfig('general/country/allow'), Mage::app()->getStore()->getId());
        $countries = array();
        foreach ($allowed as $key => $value) {
            $countriesdata = explode(",", $value);
            foreach ($countriesdata as $index => $val) {
                $countries[] = $val;
            }
        }



        /*
         * Get ship to countries
         * */
        $shipto = array();
        $carriers = Mage::getStoreConfig('carriers', Mage::app()->getStore()->getId());
        foreach ($carriers as $carrierCode => $carrierConfig) {
            if ($carrierConfig['active']) {
                if (!empty($carrierConfig['specificcountry'])) {
                    $areas = explode(',', $carrierConfig['specificcountry']);
                    foreach ($areas as $area) {
                        if (!in_array($area, $shipto)) {
                            $shipto[] = $area;
                        }
                    }
                } else {
                    $allowed = explode(",", Mage::getStoreConfig('general/country/allow'), Mage::app()->getStore()->getId());

                    foreach ($allowed as $key => $value) {
                        $countriesdata = explode(",", $value);
                        foreach ($countriesdata as $index => $val) {
                            if (!in_array($val, $shipto)) {
                                $shipto[] = $val;
                            }
                        }
                    }
                }
            }
        }

        $store_data->shipping_countries = $shipto;
        $store_data->allowed_countries = $countries;

        //get metadata per languages
        $metadata[Mage::getStoreConfig('general/locale/code', $store->getId())]['metadata']['title'] = Mage::getStoreConfig('design/head/default_title', Mage::app()->getStore()->getId());

        $keywords = explode(",", Mage::getStoreConfig('design/head/default_keywords', Mage::app()->getStore()->getId()));
        $keywordsdata = array();
        foreach ($keywords as $key => $value) {
            $keywordsdata[] = trim($value);
        }

        $metadata[Mage::getStoreConfig('general/locale/code', $store->getId())]['metadata']['keywords'] = $keywordsdata;
        $metadata[Mage::getStoreConfig('general/locale/code', $store->getId())]['metadata']['description'] = Mage::getStoreConfig('design/head/default_description', Mage::app()->getStore()->getId());
        //$metadata[Mage::getStoreConfig('general/locale/code', $store->getId())]['description']['long'] = Mage::getStoreConfig('qwindo/settings/long_store_desc', Mage::app()->getStore()->getId());
        //$metadata[Mage::getStoreConfig('general/locale/code', $store->getId())]['description']['short'] = Mage::getStoreConfig('qwindo/settings/short_store_desc', Mage::app()->getStore()->getId());


        /* $shipping1 = Mage::getStoreConfig('qwindo/shipping/usp1', Mage::app()->getStore()->getId());
          $shipping2 = Mage::getStoreConfig('qwindo/shipping/usp2', Mage::app()->getStore()->getId());
          $shipping3 = Mage::getStoreConfig('qwindo/shipping/usp3', Mage::app()->getStore()->getId());
          $shipping4 = Mage::getStoreConfig('qwindo/shipping/usp4', Mage::app()->getStore()->getId());
          $shipping5 = Mage::getStoreConfig('qwindo/shipping/usp5', Mage::app()->getStore()->getId());
          $shipping_usps = array();
          $i = 1;
          while ($i < 6) {
          $shipping_usp = ${'shipping' . $i};
          if (!empty($shipping_usp)) {
          $shipping_usps[] = $shipping_usp;
          }
          $metadata[Mage::getStoreConfig('general/locale/code', $store)]['usps']['shipping'] = $shipping_usps;
          $i++;
          }

          $global1 = Mage::getStoreConfig('qwindo/global/usp1', Mage::app()->getStore()->getId());
          $global2 = Mage::getStoreConfig('qwindo/global/usp2', Mage::app()->getStore()->getId());
          $global3 = Mage::getStoreConfig('qwindo/global/usp3', Mage::app()->getStore()->getId());
          $global4 = Mage::getStoreConfig('qwindo/global/usp4', Mage::app()->getStore()->getId());
          $global5 = Mage::getStoreConfig('qwindo/global/usp5', Mage::app()->getStore()->getId());
          $global_usps = array();
          $i = 1;
          while ($i < 6) {
          $global_usp = ${'global' . $i};
          if (!empty($global_usp)) {
          $global_usps[] = $global_usp;
          }
          $metadata[Mage::getStoreConfig('general/locale/code', $store)]['usps']['global'] = $global_usps;
          $i++;
          }

          $stock1 = Mage::getStoreConfig('qwindo/stock/usp1', Mage::app()->getStore()->getId());
          $stock2 = Mage::getStoreConfig('qwindo/stock/usp2', Mage::app()->getStore()->getId());
          $stock_usps = array();
          $i = 1;
          while ($i < 3) {
          $stock_usp = ${'stock' . $i};
          if (!empty($stock_usp)) {
          $stock_usps[] = $stock_usp;
          }
          $metadata[Mage::getStoreConfig('general/locale/code', $store)]['usps']['stock'] = $stock_usps;
          $i++;
          }

         */


        //Get tax calculation method
        switch (Mage::getStoreConfig('tax/calculation/algorithm', Mage::app()->getStore()->getId())) {
            case Mage_Tax_Model_Calculation::CALC_UNIT_BASE:
                $tax_calculation = 'unit';
                break;
            case Mage_Tax_Model_Calculation::CALC_ROW_BASE:
                $tax_calculation = 'row';
                break;
            case Mage_Tax_Model_Calculation::CALC_TOTAL_BASE:
                $tax_calculation = 'total';
                break;
            default:
                $tax_calculation = 'total';
                break;
        }


        $store_data->languages = $metadata;
        $store_data->allowed_currencies = $allowed_currencies;
        $store_data->stock_updates = Mage::getStoreConfig('qwindo/settings/stock', Mage::app()->getStore()->getId()) ? true : false;
        $store_data->including_tax = Mage::getStoreConfig('tax/calculation/price_includes_tax', Mage::app()->getStore()->getId()) ? true : false;
        $store_data->tax_calculation = $tax_calculation; //total,row or unit

        /**
         * Get Shipping tax rule
         * */
        $shipping_tax_id = Mage::getStoreConfig('tax/classes/shipping_tax_class', Mage::app()->getStore()->getId());
        $taxRules = Mage::getModel('tax/sales_order_tax')->getCollection();
        $taxCalculation = Mage::getModel('tax/calculation');
        $request = $taxCalculation->getRateRequest(null, null, null, Mage::app()->getStore());
        $shipping_tax_id = Mage::getStoreConfig('tax/classes/shipping_tax_class', Mage::app()->getStore()->getId());
        $tax_rule = new stdclass();
        $rules = array();

        $collection = Mage::getModel('tax/calculation_rule')->getCollection();
        if ($collection->getSize()) {
            $collection->addCustomerTaxClassesToResult()->addProductTaxClassesToResult()->addRatesToResult();
        }
        if ($collection->getSize()) {
            foreach ($collection as $rule) {
                $rule_data = $rule->getData();
                if (in_array($shipping_tax_id, $rule_data['product_tax_classes'])) {

                    foreach ($rule_data['tax_rates'] as $key => $rate_id) {
                        $rate = Mage::getSingleton('tax/calculation_rate')->load($rate_id);
                        $rate_info = $rate->getData();

                        $rules[$rate_info['tax_country_id']] = $rate_info['rate'];
                    }
                }
            }
        };


        $default_tax_id = Mage::getModel('customer/group')->load(0)->getTaxClassId();
        $taxRules = Mage::getModel('tax/sales_order_tax')->getCollection();
        $taxCalculation = Mage::getModel('tax/calculation');
        $request = $taxCalculation->getRateRequest(null, null, null, Mage::app()->getStore());
        $tax_rule = new stdclass();
        $rules = array();

        $collection = Mage::getModel('tax/calculation_rule')->getCollection();
        if ($collection->getSize()) {
            $collection->addCustomerTaxClassesToResult()->addProductTaxClassesToResult()->addRatesToResult();
        }

        if ($collection->getSize()) {

            foreach ($collection as $rule) {
                $rule_data = $rule->getData();
                if (in_array($default_tax_id, $rule_data['customer_tax_classes'])) {
                    foreach ($rule_data['tax_rates'] as $key => $rate_id) {
                        $rate = Mage::getSingleton('tax/calculation_rate')->load($rate_id);
                        $rate_info = $rate->getData();
                        if (Mage::getStoreConfig('tax/defaults/country', Mage::app()->getStore()->getId()) == $rate_info['tax_country_id']) {
                            $default_tax_name = $rule_data['code'];
                            $default_tax_rate = $rate_info['tax_country_id'] = $rate_info['rate'];
                        }
                    }
                }
            }
        }


        $tax_rule->id = $shipping_tax_id;
        $tax_rule->name = 'msp-shipping';
        $tax_rule->rules = $rules;
        $store_data->default_tax->name = $default_tax_name;
        $store_data->default_tax->rate = $default_tax_rate;
        $store_data->default_tax->id = $default_tax_id;

        $store_data->shipping_tax = $tax_rule;
        $store_data->rounding_policy = 'UP'; //UP, DOWN, CEILING, HALF_UP, HALF_DOWN, HALF_EVEN
        $store_data->require_shipping = true;
        $store_data->base_url = Mage::app()->getStore()->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK);
        //$store_data->logo = Mage::getBaseUrl('media') . 'theme/' . Mage::getStoreConfig('qwindo/settings/store_image', Mage::app()->getStore()->getId());
        $store_data->order_push_url = Mage::getUrl("msp/checkout/notification", array("_secure" => true)); //$store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK).'msp/standard/notification/';
        /* $store_data->email = Mage::getStoreConfig('trans_email/ident_support/email', Mage::app()->getStore()->getId());
          $store_data->contact_phone = Mage::getStoreConfig('general/store_information/phone', Mage::app()->getStore()->getId());
          $store_data->address = Mage::getStoreConfig('qwindo/address/street', Mage::app()->getStore()->getId());
          $store_data->housenumber = Mage::getStoreConfig('qwindo/address/housenumber', Mage::app()->getStore()->getId());
          $store_data->zipcode = Mage::getStoreConfig('qwindo/address/zipcode', Mage::app()->getStore()->getId());
          $store_data->city = Mage::getStoreConfig('qwindo/address/city', Mage::app()->getStore()->getId()); */
        $store_data->country = Mage::getStoreConfig('general/store_information/merchant_country'); /*
          $store_data->vat_nr = Mage::getStoreConfig('general/store_information/merchant_vat_number', Mage::app()->getStore()->getId());
          $store_data->coc = Mage::getStoreConfig('qwindo/settings/coc', Mage::app()->getStore()->getId());
          $store_data->terms_and_conditions = Mage::getStoreConfig('qwindo/settings/terms', Mage::app()->getStore()->getId());
          $store_data->faq = Mage::getStoreConfig('qwindo/settings/faq', Mage::app()->getStore()->getId());
          $store_data->open = Mage::getStoreConfig('qwindo/settings/open', Mage::app()->getStore()->getId());
          $store_data->closed = Mage::getStoreConfig('qwindo/settings/closed', Mage::app()->getStore()->getId());
          $store_data->days = array(
          "sunday" => Mage::getStoreConfig('qwindo/settings/sunday', Mage::app()->getStore()->getId()) ? true : false,
          "monday" => Mage::getStoreConfig('qwindo/settings/monday', Mage::app()->getStore()->getId()) ? true : false,
          "tuesday" => Mage::getStoreConfig('qwindo/settings/tuesday', Mage::app()->getStore()->getId()) ? true : false,
          "wednesday" => Mage::getStoreConfig('qwindo/settings/wednesday', Mage::app()->getStore()->getId()) ? true : false,
          "thursday" => Mage::getStoreConfig('qwindo/settings/thursday', Mage::app()->getStore()->getId()) ? true : false,
          "friday" => Mage::getStoreConfig('qwindo/settings/friday', Mage::app()->getStore()->getId()) ? true : false,
          "saturday" => Mage::getStoreConfig('qwindo/settings/saturday', Mage::app()->getStore()->getId()) ? true : false
          );
          $store_data->social = array(
          "facebook" => Mage::getStoreConfig('qwindo/social/facebook', Mage::app()->getStore()->getId()),
          "twitter" => Mage::getStoreConfig('qwindo/social/twitter', Mage::app()->getStore()->getId()),
          "linkedin" => Mage::getStoreConfig('qwindo/social/linkedin', Mage::app()->getStore()->getId())
          ); */
        //add store data to feed structure
        $stores[] = $store_data;
        //}

        return json_encode($store_data);
    }

    /*
     * 	Function that generates a JSON Languages feed.
     */

    public function getLanguagesFeed()
    {
        $languages = array();
        $storeCollection = Mage::getModel('core/store')->getCollection();
        foreach ($storeCollection as $store) {
            $languages[] = Mage::getStoreConfig('general/locale/code', $store->getId());
        }
        return json_encode($languages);
    }

    /*
     * 	Function that generates a JSON countries feed.
     */

    public function getCountriesFeed()
    {
        $allowed = explode(",", Mage::getStoreConfig('general/country/allow'), $store->getId());
        $countries = array();
        foreach ($allowed as $key => $value) {
            $countries[] = $value;
        }
        return json_encode($countries);
    }

    /*
     * 	Function that generates a JSON Languages feed.
     */

    public function getMetadataFeed()
    {
        $metadata = array();
        $storeCollection = Mage::getModel('core/store')->getCollection();
        foreach ($storeCollection as $store) {
            $metadata[Mage::getStoreConfig('general/locale/code', $store->getId())]['title'] = Mage::getStoreConfig('design/head/default_title', $store->getId());

            $keywords = explode(",", Mage::getStoreConfig('design/head/default_keywords', $store->getId()));
            $keywordsdata = array();
            foreach ($keywords as $key => $value) {
                $keywordsdata[] = trim($value);
            }

            $metadata[Mage::getStoreConfig('general/locale/code', $store->getId())]['keywords'] = $keywordsdata;
            $metadata[Mage::getStoreConfig('general/locale/code', $store->getId())]['description'] = Mage::getStoreConfig('design/head/default_description', $store->getId());
        }
        return json_encode($metadata);
    }

    public function handleShippingRatesNotification()
    {
        $transactionId = $this->getRequest()->getQuery('transactionid');
        $countryCode = $this->getRequest()->getQuery('countrycode');
        $zipCode = $this->getRequest()->getQuery('zipcode');
        $settings = array(
            'currency' => $this->getRequest()->getQuery('currency'),
            'country' => $this->getRequest()->getQuery('countrycode'),
            'weight' => $this->getRequest()->getQuery('weight'),
            'amount' => $this->getRequest()->getQuery('amount'),
            'size' => $this->getRequest()->getQuery('size'),
        );

        return $this->getShippingRatesFiltered($transactionId, $countryCode, $zipCode, $settings);
    }

    public function getShippingRatesFiltered($transactionId, $countryCode, $zipCode, $settings)
    {
        $output = array();

        /** @var $quote Mage_Sales_Model_Quote */
        $quote = Mage::getModel('sales/quote')->load($transactionId);

        /** @var $shippingAddress Mage_Sales_Model_Quote_Address */
        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setCountryId($countryCode);
        $shippingAddress->setPostcode($zipCode);
        $shippingAddress->setCollectShippingRates(true);

        $rates = $shippingAddress->collectShippingRates()->getGroupedAllShippingRates();


        foreach ($rates as $carrier) {
            foreach ($carrier as $rate) {
                $shipping = array();
                $shipping['id'] = $rate->getCode();
                $shipping['name'] = $rate->getCarrierTitle() . ' - ' . $rate->getMethodTitle();
                $shipping['cost'] = number_format($rate->getPrice(), 2, '.', '');
                $shipping['currency'] = $quote->getQuoteCurrencyCode();

                $output[] = $shipping;
            }
        }

        return $output;
    }

    /*
     * This function will generate the product feed, used for FastCheckout shopping
     *
     */

    public function feedAction()
    {
        $storeId = Mage::app()->getStore()->getStoreId();
        $store = $storeId = Mage::app()->getStore();
        $config = Mage::getStoreConfig('qwindo' . "/settings", $storeId);
        $headers = $this->emu_getallheaders();
        $identifier = $this->getRequest()->getQuery('identifier');

        $api_key = Mage::getStoreConfig('qwindo/settings/qwindo_key', $store->getId());
        $url = html_entity_decode(Mage::helper('core/url')->getCurrentUrl());

        $hash_id = Mage::getStoreConfig('qwindo/settings/hash_id', $store->getId());
        $timestamp = $this->microtime_float();

        //For shipping request no auth is needed so this is disabled to get working compatibility with FCO system
        if ($identifier != 'shipping') {
            $auth = explode('|', base64_decode($headers['Auth']));
            $message = $url . $auth[0] . $hash_id;
            $token = hash_hmac('sha512', $message, $api_key);
            $message = $url . $auth[0] . $hash_id;

            $this->getResponse()->clearHeaders()->setHeader('Content-Type', 'application/json', true);
            $this->getResponse()->setHeader('X-Feed-Version', '1.0', true);
            $this->getResponse()->setHeader('Shop-Type', 'Magento', true);
            $this->getResponse()->setHeader('Shop-Version', Mage::getVersion(), true);
            $this->getResponse()->setHeader('Plugin-Version', '2.4.1', true);

            if ($token !== $auth[1] and round($timestamp - $auth[0]) > 10) {
                $keys_match = false;
            } else {
                $keys_match = true;
            }
        } else {
            $keys_match = true;
        }


        if (!$config["allow_fcofeed"]) {
            $error = '{
                "success": false,
                "data": {
                    "error_code": "QW-2000",
                    "error": "You are not allowed to request the product feed."
                    }
                }'
            ;
            $this->getResponse()->setHeader('HTTP/1.0', 403, true);
            $this->getResponse()->setHttpResponseCode(403);
            $this->getResponse()->setBody($error);
            return;
        }


        if ($config["allow_fcofeed"] && $keys_match == true) {
            $identifier = $this->getRequest()->getQuery('identifier');
            if ($identifier == null) {
                $error = '{
                    "success": false,
                    "data": {
                        "error_code": "QW-1000",
                        "error": "Identifier not set."
                        }
                    }'
                ;
                $this->getResponse()->setHeader('HTTP/1.0', 500, true);
                $this->getResponse()->setHttpResponseCode(500);
                $this->getResponse()->setBody($error);
                return;
            }

            $json = '';

            switch ($identifier) {
                case "products":
                    try {
                        $json = $this->getProductsFeed();
                    } catch (Exception $e) {
                        $error = '{
                        "success": false,
                        "data": {
                            "error_code": "QW-4000",
                            "error": "Error generating product feed."
                            }
                        }'
                        ;
                        $this->getResponse()->setHeader('HTTP/1.0', 500, true);
                        $this->getResponse()->setHttpResponseCode(500);
                        $this->getResponse()->setBody($error);
                        return;
                    }
                    break;
                case "total_products":
                    try {
                        $json = $this->getTotalProductsFeed();
                    } catch (Exception $e) {
                        $error = '{
                        "success": false,
                        "data": {
                            "error_code": "QW-4001",
                            "error": "Error requesting product totals count."
                            }
                        }'
                        ;
                        $this->getResponse()->setHeader('HTTP/1.0', 500, true);
                        $this->getResponse()->setHttpResponseCode(500);
                        $this->getResponse()->setBody($error);
                        return;
                    }
                    break;
                case "categories":
                    try {
                        $json = $this->getCategoriesFeed();
                    } catch (Exception $e) {
                        $error = '{
                        "success": false,
                        "data": {
                            "error_code": "QW-6000",
                            "error": "Error generating category data feed."
                            }
                        }'
                        ;
                        $this->getResponse()->setHeader('HTTP/1.0', 500, true);
                        $this->getResponse()->setHttpResponseCode(500);
                        $this->getResponse()->setBody($error);
                        return;
                    }
                    break;
                case "stock":
                    try {
                        $json = $this->getStockFeed();
                    } catch (Exception $e) {
                        $error = '{
                        "success": false,
                        "data": {
                            "error_code": "QW-7000",
                            "error": "Error generating stock data feed."
                            }
                        }'
                        ;
                        $this->getResponse()->setHeader('HTTP/1.0', 500, true);
                        $this->getResponse()->setHttpResponseCode(500);
                        $this->getResponse()->setBody($error);
                        return;
                    }
                    break;
                case "tax":
                    $error = '{
                        "success": false,
                        "data": {
                            "error_code": "QW-9000",
                            "error": "Deprecated request."
                            }
                        }'
                    ;
                    $this->getResponse()->setHeader('HTTP/1.0', 500, true);
                    $this->getResponse()->setHttpResponseCode(500);
                    $this->getResponse()->setBody($error);
                    return;
                    break;
                case "shipping":
                    try {
                        $json = $this->getShippingFeed();
                    } catch (Exception $e) {
                        $error = '{
                        "success": false,
                        "data": {
                            "error_code": "QW-8000",
                            "error": "Error generating shipping data feed."
                            }
                        }'
                        ;
                        $this->getResponse()->setHeader('HTTP/1.0', 500, true);
                        $this->getResponse()->setHttpResponseCode(500);
                        $this->getResponse()->setBody($error);
                        return;
                    }
                    break;
                case "languages":
                    $error = '{
                        "success": false,
                        "data": {
                            "error_code": "QW-9000",
                            "error": "Deprecated request."
                            }
                        }'
                    ;
                    $this->getResponse()->setHeader('HTTP/1.0', 500, true);
                    $this->getResponse()->setHttpResponseCode(500);
                    $this->getResponse()->setBody($error);
                    return;
                    break;
                case "countries":
                    $error = '{
                        "success": false,
                        "data": {
                            "error_code": "QW-9000",
                            "error": "Deprecated request."
                            }
                        }'
                    ;
                    $this->getResponse()->setHeader('HTTP/1.0', 500, true);
                    $this->getResponse()->setHttpResponseCode(500);
                    $this->getResponse()->setBody($error);
                    return;
                    break;
                case "metadata":
                    $error = '{
                        "success": false,
                        "data": {
                            "error_code": "QW-9000",
                            "error": "Deprecated request."
                            }
                        }'
                    ;
                    $this->getResponse()->setHeader('HTTP/1.0', 500, true);
                    $this->getResponse()->setHttpResponseCode(500);
                    $this->getResponse()->setBody($error);
                    return;
                    break;
                case "stores":
                    try {
                        $json = $this->getStoresFeed();
                    } catch (Exception $e) {
                        $error = '{
                        "success": false,
                        "data": {
                            "error_code": "QW-5000",
                            "error": "Error generating shop data feed."
                            }
                        }'
                        ;
                        $this->getResponse()->setHeader('HTTP/1.0', 500, true);
                        $this->getResponse()->setHttpResponseCode(500);
                        $this->getResponse()->setBody($error);
                        return;
                    }
                    break;
            }

            $contents = gzcompress($json);
            echo $contents;
            //$this->getResponse()->setBody($json);
        } else {
            $error = '{
                    "success": false,
                    "data": {
                        "error_code": "QW-3000",
                        "error": "Signature error."
                        }
                    }'
            ;
            $this->getResponse()->setHeader('HTTP/1.0', 403, true);
            $this->getResponse()->setHttpResponseCode(403);
            $this->getResponse()->setBody($error);
            return;
        }
    }

    function microtime_float()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float) $usec + (float) $sec);
    }

    function json_readable_encode($in, $indent = 0, Closure $_escape = null)
    {
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

    public function emu_getallheaders()
    {
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$name] = $value;
            } else if ($name == "CONTENT_TYPE") {
                $headers["Content-Type"] = $value;
            } else if ($name == "CONTENT_LENGTH") {
                $headers["Content-Length"] = $value;
            }
        }
        return $headers;
    }

}
