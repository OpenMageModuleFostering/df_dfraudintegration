<?php

/**
 * Deliverynote Block
 *
 * @category	Dh
 * @package		Dh_Deliverynote
 * @author		Drew Hunter <drewdhunter@gmail.com>
 * @version		0.1.0
 */
class Df_DFraudIntegration_Block_Adminhtml_Sales_Order_Dfraud extends Mage_Adminhtml_Block_Template
{
    private $_note;

	protected function _prepareLayout()
    {
	    $onclick = "submitAndReloadArea($('dfraudintegration').parentNode, '".$this->getSubmitUrl()."')";
        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setData(array(
                'label'   => Mage::helper('sales')->__('Get DFraud Data'),
                'class'   => 'save',
                'onclick' => $onclick
            ));
        $this->setChild('submit_button', $button);
        return parent::_prepareLayout();
    }
	
	 public function getSubmitUrl()
    {
        return $this->getUrl('*/dfraud', array('order_id'=>$this->getOrder()->getId()));
    }
	
	/**
     * Retrieve order model
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        return Mage::registry('sales_order');
    }
	
	/**
	 * Based on the object being viewed i.e. order, invoice etc then 
	 * lets get the note from the order if available
	 * 
	 * @return void
	*/
    private function _initNote()
    {
		echo "asdfadsfa";exit;
		$noteId = '';
		
        if (! is_null(Mage::registry('current_order'))) {
            $noteId = Mage::registry('current_order')->getData('delivery_note_id');
        }
        elseif(! is_null(Mage::registry('current_shipment'))) {
            $noteId = Mage::registry('current_shipment')->getOrder()->getData('delivery_note_id');  
        }
        elseif(! is_null(Mage::registry('current_invoice'))) {
            $noteId = Mage::registry('current_invoice')->getOrder()->getData('delivery_note_id'); 
        }
		elseif(! is_null(Mage::registry('current_creditmemo'))) {
			$noteId = Mage::registry('current_creditmemo')->getOrder()->getData('delivery_note_id'); 
		}
		
		if ($noteId != '') {
			$this->_note = Mage::getModel('deliverynote/note')->load($noteId)->getNote();
		}
    }

	/**
	 * Initialise the delivery instruction and return
	 *
	 * @return mixed bool|string
	*/
    protected function getNote()
    {
		
       if (is_null($this->_note)) {
            $this->_initNote();
       }
	   return empty($this->_note) ? false : $this->_note;
    }
}