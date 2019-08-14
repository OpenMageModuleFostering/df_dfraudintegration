<?php

/**
 * DFraud Integration plugin.
 *
 * @category	                Df
 * @package		Df_DFraudIntegration
 * @author		Biju Thajudien <mailtobiju@gmail.com>
 * @version		0.1.0
 */
class Df_DFraudIntegration_Model_Resource_Dfraud extends Mage_Core_Model_Mysql4_Abstract
{    
    protected function _construct()
    {
		
        $this->_init('dfraudintegration/dfraud', 'dfraud_id');
    }
}