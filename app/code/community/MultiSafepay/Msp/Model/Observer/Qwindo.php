<?php

require_once dirname(__FILE__) . "/../Api/Client.php";

/**
 *
 * @category MultiSafepay
 * @package  MultiSafepay_Msp
 */
class MultiSafepay_Msp_Model_Observer_Qwindo extends MultiSafepay_Msp_Model_Observer_Abstract
{

    /**
     * The function is called before saving a product. Once the product data has changes we need to update Qwindo
     * so that up-to-date product data is used.
     *
     * */
    public function catalog_product_save_before(Varien_Event_Observer $observer)
    {
        $qwindo_enabled = Mage::getStoreConfig('qwindo/settings/allow_fcofeed');
        $qwindo_api = Mage::getStoreConfig('qwindo/settings/qwindo_api');

        if ($qwindo_api == 'test') {
            $qwindo_api_url = 'https://testapi.fastcheckout.com';
        } else {
            $qwindo_api_url = 'https://liveapi.fastcheckout.com';
        }


        if ($qwindo_enabled) {
            $observer_product = $observer->getProduct();
            $product_id = $observer_product->getId();

            $stores = array();
            $storeCollection = Mage::getModel('core/store')->getCollection();

            $json = array();
            $product = Mage::getModel('catalog/product')->load($product_id);
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
                $parentIds = Mage::getModel('catalog/product_type_grouped')->getParentIdsByChild($product->getId());
                if (!$parentIds)
                    $parentIds = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($product->getId());
            }

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

            $mainimage = new stdclass();
            $mainimage->url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product' . $product->getImage();
            $mainimage->main = true;
            $product_data['product_image_urls'][] = $mainimage;

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
                        $mainimage = new stdclass();
                        $mainimage->url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product' . $childproduct->getImage();
                        $mainimage->main = true;
                        $variant->product_image_urls = array();
                        $variant->product_image_urls[] = $mainimage;
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
                                    $product_data['options']['global_options'][$value->getId()][$language] = array(
                                        'id' => $data['option_id'],
                                        'type' => 'custom',
                                        'label' => $value->getTitle(),
                                        'values' => $values
                                    );
                                }
                            }
                        }
                    }
                }
            }


            $json = json_encode($product_data);
            $msp = new Client;
            $msp->auth = $this->getAuthorization($qwindo_api_url . '/api/products/data?id=' . $product_id, $json, $observer);
            $msp->setApiUrl($qwindo_api_url . '/api/products/data?id=' . $product_id);
            try {
                $qwindo = $msp->qwindo->post($json);
            } catch (Exception $e) {
                if (Mage::app()->getStore()->isAdmin()) {
                    Mage::getSingleton('adminhtml/session')->addError(Mage::helper('msp')->__('Error sending data to Qwindo: ' . $e->getMessage()));
                } else {
                    return $this;
                }
            }
            if ($qwindo->success == false) {
                Mage::getSingleton('adminhtml/session')->addError(Mage::helper('msp')->__('Error sending data to Qwindo: ' . $qwindo->errors->errors[0]));
            } else {
                Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('msp')->__('The product has been updated at Qwindo'));
            }
        }
        return $this;
    }

    public function process_qwindo_config_update(Varien_Event_Observer $observer)
    {
        $qwindo_enabled = Mage::getStoreConfig('qwindo/settings/allow_fcofeed');
        $qwindo_api = Mage::getStoreConfig('qwindo/settings/qwindo_api');
        if ($qwindo_api == 'test') {
            $qwindo_api_url = 'https://testapi.fastcheckout.com';
        } else {
            $qwindo_api_url = 'https://liveapi.fastcheckout.com';
        }

        if ($qwindo_enabled) {
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
            foreach ($storeCollection as $store) {
                $allowed = explode(",", Mage::getStoreConfig('general/country/allow'), $store->getId());
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
                            $allowed = explode(",", Mage::getStoreConfig('general/country/allow'), $store->getId());

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
                $metadata[Mage::getStoreConfig('general/locale/code', $store->getId())]['metadata']['title'] = Mage::getStoreConfig('design/head/default_title', $store->getId());

                $keywords = explode(",", Mage::getStoreConfig('design/head/default_keywords', $store->getId()));
                $keywordsdata = array();
                foreach ($keywords as $key => $value) {
                    $keywordsdata[] = trim($value);
                }

                $metadata[Mage::getStoreConfig('general/locale/code', $store->getId())]['metadata']['keywords'] = $keywordsdata;
                $metadata[Mage::getStoreConfig('general/locale/code', $store->getId())]['metadata']['description'] = Mage::getStoreConfig('design/head/default_description', $store->getId());
                $metadata[Mage::getStoreConfig('general/locale/code', $store->getId())]['description']['long'] = Mage::getStoreConfig('qwindo/settings/long_store_desc', $store->getId());
                $metadata[Mage::getStoreConfig('general/locale/code', $store->getId())]['description']['short'] = Mage::getStoreConfig('qwindo/settings/short_store_desc', $store->getId());


                $shipping1 = Mage::getStoreConfig('qwindo/shipping/usp1', $store->getId());
                $shipping2 = Mage::getStoreConfig('qwindo/shipping/usp2', $store->getId());
                $shipping3 = Mage::getStoreConfig('qwindo/shipping/usp3', $store->getId());
                $shipping4 = Mage::getStoreConfig('qwindo/shipping/usp4', $store->getId());
                $shipping5 = Mage::getStoreConfig('qwindo/shipping/usp5', $store->getId());
                $shipping_usps = array();
                $i = 1;
                while ($i < 6) {
                    $shipping_usp = ${'shipping' . $i};
                    if (!empty($shipping_usp)) {
                        $shipping_usps[] = $shipping_usp;
                    }
                    $metadata[Mage::getStoreConfig('general/locale/code', $store->getId())]['usps']['shipping'] = $shipping_usps;
                    $i++;
                }

                $global1 = Mage::getStoreConfig('qwindo/global/usp1', $store->getId());
                $global2 = Mage::getStoreConfig('qwindo/global/usp2', $store->getId());
                $global3 = Mage::getStoreConfig('qwindo/global/usp3', $store->getId());
                $global4 = Mage::getStoreConfig('qwindo/global/usp4', $store->getId());
                $global5 = Mage::getStoreConfig('qwindo/global/usp5', $store->getId());
                $global_usps = array();
                $i = 1;
                while ($i < 6) {
                    $global_usp = ${'global' . $i};
                    if (!empty($global_usp)) {
                        $global_usps[] = $global_usp;
                    }
                    $metadata[Mage::getStoreConfig('general/locale/code', $store->getId())]['usps']['global'] = $global_usps;
                    $i++;
                }

                $stock1 = Mage::getStoreConfig('qwindo/stock/usp1', $store->getId());
                $stock2 = Mage::getStoreConfig('qwindo/stock/usp2', $store->getId());
                $stock_usps = array();
                $i = 1;
                while ($i < 3) {
                    $stock_usp = ${'stock' . $i};
                    if (!empty($stock_usp)) {
                        $stock_usps[] = $stock_usp;
                    }
                    $metadata[Mage::getStoreConfig('general/locale/code', $store->getId())]['usps']['stock'] = $stock_usps;
                    $i++;
                }


                //Get tax calculation method 
                switch (Mage::getStoreConfig('tax/calculation/algorithm', $store->getId())) {
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
                $store_data->stock_updates = Mage::getStoreConfig('qwindo/settings/stock', $store->getId()) ? true : false;
                $store_data->including_tax = Mage::getStoreConfig('tax/calculation/price_includes_tax', $store->getId()) ? true : false;
                $store_data->tax_calculation = $tax_calculation; //total,row or unit

                /**
                 * Get Shipping tax rule
                 * */
                $shipping_tax_id = Mage::getStoreConfig('tax/classes/shipping_tax_class', $store->getId());
                $taxRules = Mage::getModel('tax/sales_order_tax')->getCollection();
                $taxCalculation = Mage::getModel('tax/calculation');
                $request = $taxCalculation->getRateRequest(null, null, null, $store);
                $shipping_tax_id = Mage::getStoreConfig('tax/classes/shipping_tax_class', $store->getId());
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

                $tax_rule->id = $shipping_tax_id;
                $tax_rule->name = 'msp-shipping';
                $tax_rule->rules = $rules;
                $store_data->shipping_tax = $tax_rule;
                $store_data->rounding_policy = 'UP'; //UP, DOWN, CEILING, HALF_UP, HALF_DOWN, HALF_EVEN
                $store_data->require_shipping = true;
                $store_data->base_url = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK);
                $store_data->logo = Mage::getBaseUrl('media') . 'theme/' . Mage::getStoreConfig('qwindo/settings/store_image', $store->getId());
                $store_data->order_push_url = Mage::getUrl("msp/checkout/notification", array("_secure" => true)); //$store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK).'msp/standard/notification/';
                $store_data->email = Mage::getStoreConfig('trans_email/ident_support/email', $store->getId());
                $store_data->contact_phone = Mage::getStoreConfig('general/store_information/phone', $store->getId());
                $store_data->address = Mage::getStoreConfig('qwindo/address/street', $store->getId());
                $store_data->housenumber = Mage::getStoreConfig('qwindo/address/housenumber', $store->getId());
                $store_data->zipcode = Mage::getStoreConfig('qwindo/address/zipcode', $store->getId());
                $store_data->city = Mage::getStoreConfig('qwindo/address/city', $store->getId());
                $store_data->country = Mage::getStoreConfig('general/store_information/merchant_country', $store->getId());
                $store_data->vat_nr = Mage::getStoreConfig('general/store_information/merchant_vat_number', $store->getId());
                $store_data->coc = Mage::getStoreConfig('qwindo/settings/coc', $store->getId());
                $store_data->terms_and_conditions = Mage::getStoreConfig('qwindo/settings/terms', $store->getId());
                $store_data->faq = Mage::getStoreConfig('qwindo/settings/faq', $store->getId());
                $store_data->open = Mage::getStoreConfig('qwindo/settings/open', $store->getId());
                $store_data->closed = Mage::getStoreConfig('qwindo/settings/closed', $store->getId());
                $store_data->days = array(
                    "sunday" => Mage::getStoreConfig('qwindo/settings/sunday', $store->getId()) ? true : false,
                    "monday" => Mage::getStoreConfig('qwindo/settings/monday', $store->getId()) ? true : false,
                    "tuesday" => Mage::getStoreConfig('qwindo/settings/tuesday', $store->getId()) ? true : false,
                    "wednesday" => Mage::getStoreConfig('qwindo/settings/wednesday', $store->getId()) ? true : false,
                    "thursday" => Mage::getStoreConfig('qwindo/settings/thursday', $store->getId()) ? true : false,
                    "friday" => Mage::getStoreConfig('qwindo/settings/friday', $store->getId()) ? true : false,
                    "saturday" => Mage::getStoreConfig('qwindo/settings/saturday', $store->getId()) ? true : false
                );
                $store_data->social = array(
                    "facebook" => Mage::getStoreConfig('qwindo/social/facebook', $store->getId()),
                    "twitter" => Mage::getStoreConfig('qwindo/social/twitter', $store->getId()),
                    "linkedin" => Mage::getStoreConfig('qwindo/social/linkedin', $store->getId())
                );
                //add store data to feed structure
                $stores[] = $store_data;
            }

            $json = json_encode($store_data);
            $msp = new Client;
            $msp->auth = $this->getAuthorization($qwindo_api_url . '/api/shop/data', $json, $observer);
            $msp->setApiUrl($qwindo_api_url . '/api/shop/data');
            $qwindo = $msp->qwindo->put($json);
            try {
                $qwindo = $msp->qwindo->put($json);
            } catch (Exception $e) {
                if (Mage::app()->getStore()->isAdmin()) {
                    Mage::getSingleton('adminhtml/session')->addError(Mage::helper('msp')->__('Error sending data to Qwindo: ' . $e->getMessage()));
                } else {
                    return $this;
                }
            }

            if ($qwindo->success == false) {
                Mage::getSingleton('adminhtml/session')->addError(Mage::helper('msp')->__('Error sending data to Qwindo: ' . $qwindo->errors->errors[0]));
            } else {
                Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('msp')->__('The Qwindo information has been submitted to Qwindo'));
            }
        }
        return $this;
    }

    public function getChildCategories($children, $category, $language, $store_id)
    {
        $children['id'] = $category->getId();
        $children['title'][$language] = $category->getName();
        if ($category->getMspCashback()) {
            $children['cashback'] = $category->getMspCashback();
        }

        if ($category->hasChildren()) {
            foreach ($category->getChildren() as $child) {
                $children['children'][] = $this->getChildCategories($children, $child, $language, $store_id);
            }
        }
        return $children;
    }

    function getCategoryTree($recursionLevel, $storeId = 1)
    {
        $parent = Mage::app()->getStore()->getRootCategoryId();
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

    public function push_category_data(Varien_Event_Observer $observer)
    {
        $qwindo_enabled = Mage::getStoreConfig('qwindo/settings/allow_fcofeed');
        $qwindo_api = Mage::getStoreConfig('qwindo/settings/qwindo_api');
        if ($qwindo_api == 'test') {
            $qwindo_api_url = 'https://testapi.fastcheckout.com';
        } else {
            $qwindo_api_url = 'https://liveapi.fastcheckout.com';
        }

        if ($qwindo_enabled) {
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



            $json = json_encode($categoryTreeData);

            $msp = new Client;
            $msp->auth = $this->getAuthorization($qwindo_api_url . '/api/categories/data', $json, $observer);
            $msp->setApiUrl($qwindo_api_url . '/api/categories/data');
            $qwindo = $msp->qwindo->post($json);
            try {
                $qwindo = $msp->qwindo->post($json);
            } catch (Exception $e) {
                if (Mage::app()->getStore()->isAdmin()) {
                    Mage::getSingleton('adminhtml/session')->addError(Mage::helper('msp')->__('Error sending data to Qwindo: ' . $e->getMessage()));
                } else {
                    return $this;
                }
            }

            if ($qwindo->success == false) {
                Mage::getSingleton('adminhtml/session')->addError(Mage::helper('msp')->__('Error sending data to Qwindo: ' . $qwindo->errors->errors[0]));
            } else {
                Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('msp')->__('The categories have been updated at Qwindo'));
            }
        }
        return $this;
    }

    /*
     * code below need validation, this is used for stock changes detection   
     */

    //On product save
    public function catalogInventorySave(Varien_Event_Observer $observer)
    {
        $qwindo_enabled = Mage::getStoreConfig('qwindo/settings/allow_fcofeed');
        $qwindo_api = Mage::getStoreConfig('qwindo/settings/qwindo_api');
        if ($qwindo_api == 'test') {
            $qwindo_api_url = 'https://testapi.fastcheckout.com';
        } else {
            $qwindo_api_url = 'https://liveapi.fastcheckout.com';
        }

        $stock_updates = Mage::getStoreConfig('qwindo/settings/stock') ? true : false;
        if ($qwindo_enabled && $stock_updates) {
            $event = $observer->getEvent();
            $_item = $event->getItem();
            //if ((int)$_item->getData('qty') != (int)$_item->getOrigData('qty')) {
            $params = array();
            $params['product_id'] = $_item->getProductId();
            $params['stock'] = (INT) $_item->getData('qty');
            $json = json_encode($params);
            $msp = new Client;
            $msp->auth = $this->getAuthorization($qwindo_api_url . '/api/stock/product', $json, $observer);
            $msp->setApiUrl($qwindo_api_url . '/api/stock/product');


            try {
                $qwindo = $msp->qwindo->put($json);
            } catch (Exception $e) {
                if (Mage::app()->getStore()->isAdmin()) {
                    Mage::getSingleton('adminhtml/session')->addError(Mage::helper('msp')->__('Error sending data to Qwindo: ' . $e->getMessage()));
                } else {
                    return $this;
                }
            }

            //}
        }
        return $this;
    }

    //on order creation
    public function subtractQuoteInventory(Varien_Event_Observer $observer)
    {
        $qwindo_enabled = Mage::getStoreConfig('qwindo/settings/allow_fcofeed');
        $stock_updates = Mage::getStoreConfig('qwindo/settings/stock') ? true : false;

        if ($qwindo_enabled && $stock_updates) {
            $quote = $observer->getEvent()->getQuote();
            foreach ($quote->getAllItems() as $item) {
                $product = Mage::getModel('catalog/product');
                $product->load($product->getIdBySku($item->getSku()));
                $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product->getId());
                $params = array();
                $params['product_id'] = $item->getProductId();
                $params['stock'] = (INT) $stockItem->getQty();

                $json = json_encode($params);
                $msp = new Client;
                $msp->auth = $this->getAuthorization('https://testapi.fastcheckout.com/api/stock/product', $json, $observer);
                $msp->setApiUrl('https://testapi.fastcheckout.com/api/stock/product');


                try {
                    $qwindo = $msp->qwindo->put($json);
                } catch (Exception $e) {
                    if (Mage::app()->getStore()->isAdmin()) {
                        Mage::getSingleton('adminhtml/session')->addError(Mage::helper('msp')->__('Error sending data to Qwindo: ' . $e->getMessage()));
                    } else {
                        return $this;
                    }
                }
                /* Mage::log('Product ID: '.$product->getId(), null, 'Qwindo-stock.log');
                  Mage::log('Stock: '. $params['stock'], null, 'Qwindo-stock.log');
                  Mage::log('-------------', null, 'Qwindo-stock.log'); */
            }
        }
        return $this;
    }

    //quote failure
    public function revertQuoteInventory(Varien_Event_Observer $observer)
    {
        $qwindo_enabled = Mage::getStoreConfig('qwindo/settings/allow_fcofeed');
        $stock_updates = Mage::getStoreConfig('qwindo/settings/stock') ? true : false;
        $qwindo_api = Mage::getStoreConfig('qwindo/settings/qwindo_api');
        if ($qwindo_api == 'test') {
            $qwindo_api_url = 'https://testapi.fastcheckout.com';
        } else {
            $qwindo_api_url = 'https://liveapi.fastcheckout.com';
        }

        if ($qwindo_enabled && $stock_updates) {
            $quote = $observer->getEvent()->getQuote();
            foreach ($quote->getAllItems() as $item) {
                $product = Mage::getModel('catalog/product');
                $product->load($product->getIdBySku($item->getSku()));
                $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product->getId());
                $params = array();
                $params['product_id'] = $item->getProductId();
                $params['stock'] = (INT) $stockItem->getQty();
                $json = json_encode($params);
                $msp = new Client;
                $msp->auth = $this->getAuthorization($qwindo_api_url . '/api/stock/product', $json, $observer);
                $msp->setApiUrl($qwindo_api_url . '/api/stock/product');


                try {
                    $qwindo = $msp->qwindo->put($json);
                } catch (Exception $e) {
                    if (Mage::app()->getStore()->isAdmin()) {
                        Mage::getSingleton('adminhtml/session')->addError(Mage::helper('msp')->__('Error sending data to Qwindo: ' . $e->getMessage()));
                    } else {
                        return $this;
                    }
                }
            }
        }

        return $this;
    }

    //on cancellation
    public function cancelOrderItem(Varien_Event_Observer $observer)
    {
        $qwindo_enabled = Mage::getStoreConfig('qwindo/settings/allow_fcofeed');
        $qwindo_api = Mage::getStoreConfig('qwindo/settings/qwindo_api');
        if ($qwindo_api == 'test') {
            $qwindo_api_url = 'https://testapi.fastcheckout.com';
        } else {
            $qwindo_api_url = 'https://liveapi.fastcheckout.com';
        }
        $stock_updates = Mage::getStoreConfig('qwindo/settings/stock') ? true : false;

        if ($qwindo_enabled && $stock_updates) {
            $item = $observer->getEvent()->getItem();
            $product = Mage::getModel('catalog/product');
            $product->load($product->getIdBySku($item->getSku()));
            $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product->getId());
            $params = array();
            $params['product_id'] = $item->getProductId();
            $params['stock'] = (INT) $stockItem->getQty();
            $json = json_encode($params);
            $msp = new Client;
            $msp->auth = $this->getAuthorization($qwindo_api_url . '/api/stock/product', $json, $observer);
            $msp->setApiUrl($qwindo_api_url . '/api/stock/product');
            try {
                $qwindo = $msp->qwindo->put($json);
            } catch (Exception $e) {
                if (Mage::app()->getStore()->isAdmin()) {
                    Mage::getSingleton('adminhtml/session')->addError(Mage::helper('msp')->__('Error sending data to Qwindo: ' . $e->getMessage()));
                } else {
                    return $this;
                }
            }
        }
        return $this;
    }

    //on creditmemo creation
    public function refundOrderInventory(Varien_Event_Observer $observer)
    {
        $qwindo_enabled = Mage::getStoreConfig('qwindo/settings/allow_fcofeed');
        $stock_updates = Mage::getStoreConfig('qwindo/settings/stock') ? true : false;
        $qwindo_api = Mage::getStoreConfig('qwindo/settings/qwindo_api');
        if ($qwindo_api == 'test') {
            $qwindo_api_url = 'https://testapi.fastcheckout.com';
        } else {
            $qwindo_api_url = 'https://liveapi.fastcheckout.com';
        }

        if ($qwindo_enabled && $stock_updates) {
            $creditmemo = $observer->getEvent()->getCreditmemo();
            foreach ($creditmemo->getAllItems() as $item) {
                $product = Mage::getModel('catalog/product');
                $product->load($product->getIdBySku($item->getSku()));
                $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product->getId());
                $params = array();
                $params['product_id'] = $item->getProductId();
                $params['stock'] = (INT) $stockItem->getQty();
                $json = json_encode($params);
                $msp = new Client;
                $msp->auth = $this->getAuthorization($qwindo_api_url . '/api/stock/product', $json, $observer);
                $msp->setApiUrl($qwindo_api_url . '/api/stock/product');
                try {
                    $qwindo = $msp->qwindo->put($json);
                } catch (Exception $e) {
                    if (Mage::app()->getStore()->isAdmin()) {
                        Mage::getSingleton('adminhtml/session')->addError(Mage::helper('msp')->__('Error sending data to Qwindo: ' . $e->getMessage()));
                    } else {
                        return $this;
                    }
                }
            }
        }
        return $this;
    }

    public function getAuthorization($url, $data, $observer)
    {
        $hash_id = Mage::getStoreConfig('qwindo/settings/hash_id');
        $qwindo_key = Mage::getStoreConfig('qwindo/settings/qwindo_key');
        $timestamp = $this->microtime_float();
        $token = hash_hmac('sha512', $url . $timestamp . $data, $qwindo_key);
        $auth = base64_encode(sprintf('%s:%s:%s', $hash_id, $timestamp, $token));

        /* Mage::log('Hash ID: '.$hash_id, null, 'Qwindo-auth.log');
          Mage::log('Qwindo ID: '.$qwindo_key, null, 'Qwindo-auth.log');
          Mage::log('Timestamp: '.$timestamp, null, 'Qwindo-auth.log');
          Mage::log('Data: '.$data, null, 'Qwindo-auth.log');
          Mage::log('URL: '.$url, null, 'Qwindo-auth.log');
          Mage::log('-----------', null, 'Qwindo-auth.log'); */
        return $auth;
    }

    public function microtime_float()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float) $usec + (float) $sec);
    }

}
