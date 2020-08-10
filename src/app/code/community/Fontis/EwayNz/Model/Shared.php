<?php
/**
 * Fontis eWAY NZ payment gateway
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so you can be sent a copy immediately.
 *
 * @category   Fontis
 * @package    Fontis_EwayNz
 * @copyright  Copyright (c) 2010 Fontis (http://www.fontis.com.au)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Fontis_EwayNz_Model_Shared extends Mage_Payment_Model_Method_Abstract
{
    protected $_code  = 'ewaynz';

    protected $_isGateway               = false;
    protected $_canAuthorize            = false;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = false;
    protected $_canRefund               = false;
    protected $_canVoid                 = false;
    protected $_canUseInternal          = false;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = false;

    protected $_formBlockType = 'ewaynz/form';

    protected $_order;

    /**
     * Get order model
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        if (!$this->_order) {
            $paymentInfo = $this->getInfoInstance();
            $this->_order = Mage::getModel('sales/order')
                            ->loadByIncrementId($paymentInfo->getOrder()->getRealOrderId());
        }
        return $this->_order;
    }

    /**
     * Get Customer Id
     *
     * @return string
     */
    public function getCustomerId()
    {
        return Mage::getStoreConfig('payment/' . $this->getCode() . '/customer_id');
    }
    
    /**
     * Get Username
     *
     * @return string
     */
    public function getUserName()
    {
        return Mage::getStoreConfig('payment/' . $this->getCode() . '/username');
    }
    
//    
    public function getStoreInfoByField($field)
    {
        return Mage::getStoreConfig('payment/' . $this->getCode() . '/'.$field);
    }
//    
    
    public function getRequestUrl()
    {
         return 'https://nz.ewaygateway.com/Request/';
    }
    
    public function getResultsUrl()
    {
         return 'https://nz.ewaygateway.com/Result/';
    }



    /**
     * Get currency that accepted by eWAY account
     *
     * @return string
     */
    public function getAcceptedCurrency()
    {
//        return Mage::getStoreConfig('payment/' . $this->getCode() . '/currency');
        return array('NZD'); //explode( ',', Mage::getStoreConfig('payment/' . $this->getCode() . '/currency') );
    }

    public function validate()
    {
        parent::validate();

		$currency_code = Mage::app()->getStore()->getCurrentCurrencyCode();
        
        $paymentInfo = $this->getInfoInstance();
//        if ($paymentInfo instanceof Mage_Sales_Model_Order_Payment) {
        if (!$currency_code && $paymentInfo instanceof Mage_Sales_Model_Order_Payment) {
			$currency_code = $paymentInfo->getOrder()->getBaseCurrencyCode();
			
        } elseif(!$currency_code) {
            $currency_code = $paymentInfo->getQuote()->getBaseCurrencyCode();
        }
        
//        if ($currency_code != $this->getAcceptedCurrency()) {
       	if( !in_array($currency_code, $this->getAcceptedCurrency()) ) {        	
            Mage::throwException(Mage::helper('ewaynz')->__('Selected currency code ('.$currency_code.') is not compatabile with eWAY'));
//            Mage::throwException(Mage::helper('ewaynz')->__(var_export($paymentInfo->getData(),true)));
        }
        
        return $this;
    }

    public function getOrderPlaceRedirectUrl()
    {
          return Mage::getUrl('ewaynz/shared/redirect');
    }

    /**
     * Sends data to eWAY and requests a transaction URI to redirect the customer to.
     */
    public function getTransactionUri()
    {
        $url = $this->getRequestString();
        
        $response = null;
        if(extension_loaded('curl')) {
            $ch = curl_init();
		    curl_setopt($ch, CURLOPT_URL, $url);
		    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		    $response = curl_exec($ch);
		    curl_close($ch);
		    
		} elseif(ini_get('allow_url_fopen')) {
		    $response = file($url);
		}

		$xml = simplexml_load_string($response);
		$result = $xml->Result;
		
		if($result == 'True') {
		    return (string)$xml->URI;
		} else {
		    return false;
		}
    }

    public function getRequestString()
    {
        $billing = $this->getOrder()->getBillingAddress();
        $fields = array();
        $invoiceDesc = '';
        $lengs = 0;
        foreach ($this->getOrder()->getAllItems() as $item) {
            if ($item->getParentItem()) {
                continue;
            }
            if (Mage::helper('core/string')->strlen($invoiceDesc . $item->getName()) > 10000) {
                break;
            }
            $invoiceDesc .= $item->getName() . ', ';
        }
        $invoiceDesc = Mage::helper('core/string')->substr($invoiceDesc, 0, -2);
        
        // Required information
        $fields['CustomerID'] = $this->getCustomerId();
        $fields['UserName'] = $this->getUserName();
        $fields['Amount'] = sprintf("%.02f", $this->getOrder()->getBaseGrandTotal());
        $fields['Currency'] = $this->getOrder()->getBaseCurrencyCode();
        $fields['ReturnURL'] = Mage::getUrl('ewaynz/shared/success', array('_secure' => true));
        $fields['CancelURL'] = Mage::getUrl('ewaynz/shared/failure', array('_secure' => true));
        
        // Customer information
        $fields['CustomerFirstName'] = $billing->getFirstname();
        $fields['CustomerLastName'] = $billing->getLastname();
        $fields['CustomerAddress'] = trim(str_replace("\n", ' ', trim(implode(' ', $billing->getStreet()))));
        $fields['CustomerCity'] = $billing->getCity();
        $fields['CustomerState'] = $billing->getRegion();
        $fields['CustomerPostCode'] = $billing->getPostcode();
        $fields['CustomerCountry'] = $billing->getCountryId();
        $fields['CustomerEmail'] = $this->getOrder()->getCustomerEmail();
        $fields['CustomerPhone'] = $billing->getTelephone();
        $fields['InvoiceDescription'] = htmlspecialchars($invoiceDesc);
        $fields['MerchantReference'] = $this->getOrder()->getRealOrderId();
        $fields['MerchantOption1'] = '';
        $fields['MerchantOption2'] = Mage::helper('core')->encrypt($fields['MerchantReference']);
        $fields['MerchantOption3'] = '';

        // Store information
        $fields['CompanyName'] = Mage::app()->getStore()->getName();

        if($logo = $this->getStoreInfoByField('logo')) {
            $fields['CompanyLogo'] = $logo;
        }

        if($banner = $this->getStoreInfoByField('banner')) {
            $fields['PageBanner'] = $banner;
        }

        // Process the fields array into something that can be sent via POST
        $request = array();
        foreach ($fields as $key => $value) {
            $request[] = $key . '=' . rawurlencode($value);
        }
        
        $url = $this->getRequestUrl() . '?' . implode('&', $request);
        
        return $url;
    }

    /**
     * Get debug flag
     *
     * @return string
     */
    public function getDebug()
    {
        return Mage::getStoreConfig('payment/' . $this->getCode() . '/debug_flag');
    }

    public function capture(Varien_Object $payment, $amount)
    {
        $payment->setStatus(self::STATUS_APPROVED)
            ->setLastTransId($this->getTransactionId());

        return $this;
    }

    public function cancel(Varien_Object $payment)
    {
        $payment->setStatus(self::STATUS_DECLINED)
            ->setLastTransId($this->getTransactionId());

        return $this;
    }

    /**
     * parse response POST array from gateway page and return payment status
     *
     * @return bool
     */
    public function getResponse($code)
    {
        $url = $this->getResultsUrl() . 
            '?CustomerID=' . $this->getCustomerId() . 
            '&UserName=' . $this->getUserName() . 
            '&AccessPaymentCode=' . $code;

        $response = null;
        if(extension_loaded('curl')) {
            $ch = curl_init();
		    curl_setopt($ch, CURLOPT_URL, $url);
		    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		    $response = curl_exec($ch);
		    curl_close($ch);
		    
		} elseif(ini_get('allow_url_fopen')) {
		    $response = file($url);
		}

		$xml = simplexml_load_string($response);
		return Mage::helper('core')->xmlToAssoc($xml);
    }

}
