<?php
/**
 * DFraud Integration plugin.
 *
 * @category	                Df
 * @package		Df_DFraudIntegration
 * @author		Biju Thajudien <mailtobiju@gmail.com>
 * @version		0.1.0
 */
class Df_DFraudIntegration_Adminhtml_DfraudController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Initialize order model instance
     *
     * @return Mage_Sales_Model_Order || false
     */
    protected function _initOrder()
    {
        $id = $this->getRequest()->getParam('order_id');
        $order = Mage::getModel('sales/order')->load($id);

        if (!$order->getId()) {
            $this->_getSession()->addError($this->__('This order no longer exists.'));
            $this->_redirect('*/*/');
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
            return false;
        }
        Mage::register('sales_order', $order);
        Mage::register('current_order', $order);
		
		echo "<pre>";print_r($shippingDetails);
		
		return $order;
    }

    /**
     * Index action
     */
    public function indexAction()
    {
		 if ($order = $this->_initOrder()) {
			 //echo "<pre>";print_r($order->customer_email);
			$remote_ip = "197.79.0.3";//$order->getRemoteIp();
			
			$helper = Mage::helper('dfraudintegration');
		    try {
                $response = false;
				$ipLocation = $helper->getIpLocation($remote_ip);
				
				$shippingId =  $order->getShippingAddressId();
				$billingId =  $order->getBillingAddressId();
				
				$addressMismatch = $helper->checkAddress($shippingId, $billingId, $ipLocation);
				$email_result = $helper->checkEmailValid($order->customer_email);
				
				
			}
			catch (Mage_Core_Exception $e) {
                $response = array(
                    'error'     => true,
                    'message'   => $e->getMessage(),
                );
            }
            catch (Exception $e) {
                $response = array(
                    'error'     => true,
                    'message'   => $this->__('Cannot get dfraud data.')
                );
            }
		 }
       
    }

   
}
?>