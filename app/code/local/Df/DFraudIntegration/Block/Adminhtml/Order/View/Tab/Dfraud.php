<?php
/**
 * DFraud Integration plugin.
 *
 * @category	                Df
 * @package		Df_DFraudIntegration
 * @author		Biju Thajudien <mailtobiju@gmail.com>
 * @version		0.1.0
 */
class Df_DFraudIntegration_Block_Adminhtml_Order_View_Tab_Dfraud
    extends Mage_Adminhtml_Block_Template
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    protected $_chat = null;

    protected function _construct()
    {
        parent::_construct();
		$result = $this->getFraudDetectionData();
		
		Mage::register('data', $result);
        $this->setTemplate('dfraudintegration/dfraud.phtml');
    }

    public function getTabLabel() {
        return $this->__('Fraud Detection');
    }

    public function getTabTitle() {
        return $this->__('Fraud Detection');
    }

    public function canShowTab() {
        return true;
    }

    public function isHidden() {
        return false;
    }

    public function getOrder(){
        return Mage::registry('current_order');
    }
	
	public function getFraudDetectionData(){
		 if ($order = $this->getOrder()) {
			//echo "<pre>";print_r($order->grand_total);exit;
			$remote_ip =  $order->getRemoteIp();
			
			$helper = Mage::helper('dfraudintegration');
		    try {
                $response = false;
				$ipLocation = $helper->getIpLocation($remote_ip);
				
				$shippingId =  $order->getShippingAddressId();
				$billingId =  $order->getBillingAddressId();
				
				$billingDetails = Mage::getModel('sales/order_address')->load($billingId);
				
				$resource = Mage::getSingleton('core/resource');
				$readConnection = $resource->getConnection('core_read');
				$query = 'SELECT * FROM ' . $resource->getTableName('dfraudintegration/scores');
				$scores = $readConnection->fetchAll($query);
					
				//Perform address and IP checks
				$result = $helper->checkAddress($shippingId, $billingId, $ipLocation, $scores);
				
				//Check previous orders from user and ip
				$order_history = $helper->getOrderHistory($order->customer_id, $order->getRemoteIp(), $scores);
				//echo "<pre>";print_r($result);
				$result['order_history_cust'] = $order_history;
				
				//Check CC bin data
				$payments = $order->getAllPayments();
    			$binData = $helper->getBinData($payments, $remote_ip, $billingDetails['country_id'], $scores);
    			$result['bin'] = $binData;
				
				//Check the order amount
				$amountData = $helper->checkOrderAmount($order->grand_total, $scores);
				$result['ammount_check'] = $amountData;
				
				//Get the score summary
				$result['score'] = $helper->getResultTotalScore($result);
				$summary = array_merge((array)$result['summary'], (array)$order_history['summary']
														, (array)$binData['summary']
														, (array)$amountData['summary']);
				$summaryDesc = $helper->getRiskScoreDescription($scores, $summary);
				$result['summary'] = $summaryDesc;
				
				return $result;
			
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