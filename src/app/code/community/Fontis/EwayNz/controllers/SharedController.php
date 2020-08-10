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

class Fontis_EwayNz_SharedController extends Mage_Core_Controller_Front_Action
{
    protected function _expireAjax()
    {
        if (!$this->getCheckout()->getQuote()->hasItems()) {
            $this->getResponse()->setHeader('HTTP/1.1','403 Session Expired');
            exit;
        }
    }

    /**
     * Get singleton of Checkout Session Model
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * when customer select eWay payment method
     */
    public function redirectAction()
    {
        $session = $this->getCheckout();
        $session->setEwayNzQuoteId($session->getQuoteId());
        $session->setEwayNzRealOrderId($session->getLastRealOrderId());

        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($session->getLastRealOrderId());
        $order->addStatusToHistory($order->getStatus(), Mage::helper('ewaynz')->__('Customer was redirected to eWAY.'));
        $order->save();

        $shared = $order->getPayment()->getMethodInstance();
        $url = $shared->getTransactionUri();

        if($url !== false) {
            $this->getResponse()->setRedirect($url, 302);
        } else {
            $this->_forward('*/*/failure');
        }

        $session->unsQuoteId();
    }

    /**
     * eWay returns POST variables to this action
     */
    public function successAction()
    {    
        $result = $this->_getPaymentResult();
        
        if($result['TrxnStatus'] != 'true') {
        	$this->_redirect('checkout/onepage/failure');

        } else {

	        $session = $this->getCheckout();
	
	        $session->unsEwayNzRealOrderId();
	        $session->setQuoteId($session->getEwayNzQuoteId(true));
	        $session->getQuote()->setIsActive(false)->save();
	
	        $order = Mage::getModel('sales/order');
	        $order->load($this->getCheckout()->getLastOrderId());
	        if($order->getId()) {
	            $order->sendNewOrderEmail();
	        }
	
	        $this->_redirect('checkout/onepage/success');
        }
    }

    /**
     * Display failure page if error
     *
     */
    public function failureAction()
    {
        $result = $this->_getPaymentResult();
        
        $this->getCheckout()->clear();

        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Checking POST variables.
     * Creating invoice if payment was successfull or cancel order if payment was declined
     */
    protected function _getPaymentResult()
    {
        if (!$this->getRequest()->isPost()) {
            $this->norouteAction();
            return;
        }
        
        $accessPaymentCode = $this->getRequest()->getPost('AccessPaymentCode');
        $response = Mage::getModel('ewaynz/shared')->getResponse($accessPaymentCode);

        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($response['MerchantReference']);

        $paymentInst = $order->getPayment()->getMethodInstance();

        if ($response['TrxnStatus'] == 'true') {
            if ($order->canInvoice()) {
                $invoice = $order->prepareInvoice();
                $invoice->register()->capture();
                Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder())
                    ->save();

                $paymentInst->setTransactionId($response['TrxnNumber']);
                $text = "Customer successfully returned from eWAY.<br />Transaction reference: " . $response['TrxnNumber'] . "<br />Response: " . $response['TrxnResponseMessage'];
                $order->addStatusToHistory($order->getStatus(), Mage::helper('ewaynz')->__($text));
            }
        } else {
            $paymentInst->setTransactionId($response['TrxnNumber']);
            $order->cancel();
            $order->addStatusToHistory($order->getStatus(), Mage::helper('ewaynz')->__('Customer was rejected by eWAY.<br />Response Code: ' . $response['ResponseCode'] . '<br />Response: ' . $response['TrxnResponseMessage']));
            $this->getCheckout()->setEwayNzErrorMessage($response['TrxnResponseMessage']);
        }

        $order->save();

        return $response;
    }
}
