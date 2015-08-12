<?php
/*
 * @category    VMalanka_
 * @package     VMalanka_CleanCheckout
 * @author      Vasyl Malanka <vasyl.malanka@yahoo.com>
 */

require_once(Mage::getModuleDir('controllers', 'Mage_Checkout') . DS . 'OnepageController.php');
class VMalanka_CleanCheckout_OnepageController extends  Mage_Checkout_OnepageController
{
    /**
     * Checkout page
     */
    public function indexAction()
    {
        // Go to review page
        $this->_forward('cleancheckout');
    }

    /**
     * Review page action
     */
    public function cleancheckoutAction()
    {
        $this->_prepareQuoteData();

        $this->loadLayout();
        // Set page title
        $this->getLayout()->getBlock('head')->setTitle($this->__('Checkout'));
        $this->renderLayout();
    }

    /**
     * Set checkout steps data
     */
    protected function _prepareQuoteData()
    {
        $quote = Mage::getSingleton('checkout/type_onepage')->getQuote();

        if ($quote->getCustomer()->getDefaultBillingAddress()) {
            $address = Mage::getModel('customer/address')
                ->load($quote->getCustomer()->getDefaultBillingAddress()->getId());
            $addressData = array(
                'firstname'     => $quote->getCustomerFirstname(),
                'lastname'      => $quote->getCustomerLastname(),
                'street'        => implode(' ', $address->getStreet()),
                'city'          => $address->getCity(),
                'postcode'      => $address->getPostcode(),
                'telephone'     => $address->getTelephone(),
                'country_id'    => $address->getCountryId(),
                'region_id'     => $address->getRegionId()
            );
        } else {
            $addressData = array(
                'firstname'     => $quote->getCustomerFirstname(),
                'lastname'      => $quote->getCustomerLastname(),
                'street'        => 'No Street',
                'city'          => 'No City',
                'postcode'      => 'No Postcode',
                'telephone'     => 'No Phone Number',
                'country_id'    => 'DK',
                'region_id'     => 'No Region'
            );
        }
        $quote->getBillingAddress()->addData($addressData);

        if ($quote->getCustomer()->getDefaultShippingAddress()) {
            $address = Mage::getModel('customer/address')
                ->load($quote->getCustomer()->getDefaultShippingAddress()->getId());
            $addressData = array(
                'firstname'     => $quote->getCustomerFirstname(),
                'lastname'      => $quote->getCustomerLastname(),
                'street'        => implode(' ', $address->getStreet()),
                'city'          => $address->getCity(),
                'postcode'      => $address->getPostcode(),
                'telephone'     => $address->getTelephone(),
                'country_id'    => $address->getCountryId(),
                'region_id'     => $address->getRegionId()
            );
        } else {
            $addressData = array(
                'firstname'     => $quote->getCustomerFirstname(),
                'lastname'      => $quote->getCustomerLastname(),
                'street'        => 'No Street',
                'city'          => 'No City',
                'postcode'      => 'No Postcode',
                'telephone'     => 'No Phone Number',
                'country_id'    => 'DK',
                'region_id'     => 'No Region'
            );
        }
        $shippingAddress = $quote->getShippingAddress()->addData($addressData);

        $shippingAddress
            ->setCollectShippingRates(true)
            ->collectShippingRates()
            ->setShippingMethod('flatrate_flatrate')
            ->setPaymentMethod('checkmo');

        $quote->getPayment()->importData(array('method' => 'checkmo'));

        $quote->collectTotals()->save();

        Mage::getSingleton('customer/session')->setQuoteDataPrepared(true);
    }

    /**
     * Create order action
     */
    public function saveOrderAction()
    {
        if (!$this->_validateFormKey()) {
            $this->_redirect('*/*');
            return;
        }

        $result = array();
        try {
            $requiredAgreements = Mage::helper('checkout')->getRequiredAgreementIds();
            if ($requiredAgreements) {
                $postedAgreements = array_keys($this->getRequest()->getPost('agreement', array()));
                $diff = array_diff($requiredAgreements, $postedAgreements);
                if ($diff) {
                    $result['success'] = false;
                    $result['error'] = true;
                    $result['error_messages'] = $this->__('Please agree to all the terms and conditions before placing the order.');
                    $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
                    return;
                }
            }

            $data = $this->getRequest()->getPost('payment', array());
            if ($data) {
                $data['checks'] = Mage_Payment_Model_Method_Abstract::CHECK_USE_CHECKOUT
                    | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_COUNTRY
                    | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_CURRENCY
                    | Mage_Payment_Model_Method_Abstract::CHECK_ORDER_TOTAL_MIN_MAX
                    | Mage_Payment_Model_Method_Abstract::CHECK_ZERO_TOTAL;
                $this->getOnepage()->getQuote()->getPayment()->importData($data);
            }

            $this->getOnepage()->saveOrder();

            $redirectUrl = $this->getOnepage()->getCheckout()->getRedirectUrl();
            $result['success'] = true;
            $result['error']   = false;
        } catch (Mage_Payment_Model_Info_Exception $e) {
            $message = $e->getMessage();
            if (!empty($message)) {
                $result['error_messages'] = $message;
            }
            $result['goto_section'] = 'payment';
            $result['update_section'] = array(
                'name' => 'payment-method',
                'html' => $this->_getPaymentMethodsHtml()
            );
        } catch (Mage_Core_Exception $e) {
            Mage::logException($e);
            Mage::helper('checkout')->sendPaymentFailedEmail($this->getOnepage()->getQuote(), $e->getMessage());
            $result['success'] = false;
            $result['error'] = true;
            $result['error_messages'] = $e->getMessage();

            $gotoSection = $this->getOnepage()->getCheckout()->getGotoSection();
            if ($gotoSection) {
                $result['goto_section'] = $gotoSection;
                $this->getOnepage()->getCheckout()->setGotoSection(null);
            }
            $updateSection = $this->getOnepage()->getCheckout()->getUpdateSection();
            if ($updateSection) {
                if (isset($this->_sectionUpdateFunctions[$updateSection])) {
                    $updateSectionFunction = $this->_sectionUpdateFunctions[$updateSection];
                    $result['update_section'] = array(
                        'name' => $updateSection,
                        'html' => $this->$updateSectionFunction()
                    );
                }
                $this->getOnepage()->getCheckout()->setUpdateSection(null);
            }
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::helper('checkout')->sendPaymentFailedEmail($this->getOnepage()->getQuote(), $e->getMessage());
            $result['success']  = false;
            $result['error']    = true;
            $result['error_messages'] = $this->__('There was an error processing your order. Please contact us or try again later.');
        }
        $this->getOnepage()->getQuote()->save();
        /**
         * when there is redirect to third party, we don't want to save order yet.
         * we will save the order in return action.
         */
        if (isset($redirectUrl)) {
            $result['redirect'] = $redirectUrl;
        }

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }
}
