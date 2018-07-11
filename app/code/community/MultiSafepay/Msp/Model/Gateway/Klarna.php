<?php

/**
 *
 * @category MultiSafepay
 * @package  MultiSafepay_Msp
 */
class MultiSafepay_Msp_Model_Gateway_Klarna extends MultiSafepay_Msp_Model_Gateway_Abstract {

    protected $_code = "msp_klarna";
    public $_model = "klarna";
    public $_gateway = "KLARNA";
    protected $_formBlockType = 'msp/klarna';
    protected $_canUseCheckout = true;


    public $giftcards = array(
        'msp_webgift',
        'msp_ebon',
        'msp_babygiftcard',
        'msp_boekenbon',
        'msp_erotiekbon',
        'msp_parfumnl',
        'msp_parfumcadeaukaart',
        'msp_degrotespeelgoedwinkel',
        'msp_yourgift',
        'msp_wijncadeau',
        'msp_lief',
        'msp_gezondheidsbon',
        'msp_fashioncheque',
        'msp_fashiongiftcard',
        'msp_podium',
        'msp_vvvgiftcard',
        'msp_sportenfit',
        'msp_beautyandwellness',
    );

    
    public $gateways = array(
        'msp_ideal',
        'msp_payafter',
        'msp_klarna',
        'msp_mistercash',
        'msp_visa',
        'msp_mastercard',
        'msp_banktransfer',
        'msp_maestro',
        'msp_paypal',
        'msp_giropay',
        'msp_multisafepay',
        'msp_directebanking',
        'msp_directdebit',
        'msp_amex',
     
    );
    public function __construct() {
        $availableByIP = true;
        if (Mage::getStoreConfig('msp_gateways/msp_klarna/ip_check')) {
            if ($this->_isTestMode()) {
                $data = Mage::getStoreConfig('msp_gateways/msp_klarna/ip_filter_test');
            } else {
                $data = Mage::getStoreConfig('msp_gateways/msp_klarna/ip_filter');
            }

            if (!in_array($_SERVER["REMOTE_ADDR"], explode(';', $data))) {
                $availableByIP = false;
            }
        }

        if(in_array($this->_code, $this->gateways)){
		        $this->_configCode = 'msp_gateways';
		        $this->_module = 'msp_gateways';
            	$currencies = explode(',', Mage::getStoreConfig('msp_gateways/' . $this->_code . '/allowed_currency'));
				$isAllowConvert = Mage::getStoreConfigFlag('msp/settings/allow_convert_currency');
			}elseif(in_array($this->_code, $this->giftcards)){   
				$this->_configCode = 'msp_giftcards';  
				$this->_module = 'msp_giftcards';       	
				$currencies = explode(',', Mage::getStoreConfig('msp_giftcards/' . $this->_code . '/allowed_currency'));
				$isAllowConvert = Mage::getStoreConfigFlag('msp/settings/allow_convert_currency');
			}

        if ($isAllowConvert) {
            $availableByCurrency = true;
        } else {
            if (in_array(Mage::app()->getStore()->getCurrentCurrencyCode(), $currencies)) {
                $availableByCurrency = true;
            } else {
                $availableByCurrency = false;
            }
        }
        $this->_canUseCheckout = $availableByIP && $availableByCurrency;
    }

    public function getOrderPlaceRedirectUrl() {
        if (isset($_POST['payment']['birthday'])) {
            $birthday = $_POST['payment']['birthday'];
        } else {
            $birthday = '';
        }

        if (isset($_POST['payment']['accountnumber'])) {
            $accountnumber = $_POST['payment']['accountnumber'];
        } else {
            $accountnumber = '';
        }

        if (isset($_POST['payment']['gender'])) {
            $gender = $_POST['payment']['gender'];
        } else {
            $gender = '';
        }

		
        $url = $this->getModelUrl("msp/standard/redirect/issuer/" . $this->_issuer);
        if (!strpos($url, "?"))
            $url .= '?birthday=' . $birthday . '&accountnumber=' . $accountnumber . '&gender=' . $gender;
        else
            $url .= '&birthday=' . $birthday . '&accountnumber=' . $accountnumber . '&gender=' . $gender;
        return $url;
    }

    /**
     * Is Test Mode
     *
     * @param null|integer|Mage_Core_Model_Store $store
     * @return bool
     */
    protected function _isTestMode($store = null) {
        $mode = Mage::getStoreConfig('msp_gateways/msp_klarna/test_api_pad', $store);

        return $mode == MultiSafepay_Msp_Model_Config_Sources_Accounts::TEST_MODE;
    }

}
