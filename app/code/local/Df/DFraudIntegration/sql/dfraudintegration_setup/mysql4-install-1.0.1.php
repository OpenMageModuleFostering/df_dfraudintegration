<?php
/**
 * DFraud Integration plugin.
 *
 * @category	                Df
 * @package		Df_DFraudIntegration
 * @author		Biju Thajudien <mailtobiju@gmail.com>
 * @version		0.1.0
 */
 
/**
 * @var $installer Mage_Core_Model_Resource_Setup
 */
$installer = $this;

/**
 * Creating table magentostudy_news
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('dfraudintegration/highriskcountries'))
	 ->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'unsigned' => true,
        'identity' => true,
        'nullable' => false,
        'primary'  => true,
    ), 'Entity id')
    ->addColumn('country_id', Varien_Db_Ddl_Table::TYPE_TEXT, 2, array(
        'nullable' => true,
    ), 'Title')
    ->addColumn('country', Varien_Db_Ddl_Table::TYPE_TEXT, 63, array(
        'nullable' => true,
        'default'  => null,
    ), 'Author')
	->addColumn('region', Varien_Db_Ddl_Table::TYPE_TEXT, 63, array(
        'nullable' => true,
        'default'  => null,
    ), 'Author')
	->addIndex($installer->getIdxName(
            $installer->getTable('dfraudintegration/highriskcountries'),
            array('id'),
            Varien_Db_Adapter_Interface::INDEX_TYPE_INDEX
        ),
        array('id'),
        array('type' => Varien_Db_Adapter_Interface::INDEX_TYPE_INDEX)
    )
	
    ->setComment('Dfraud High Risk Countries');

$installer->getConnection()->createTable($table);

//Ukraine, Indonesia, Yugoslavia, Lithuania, Egypt, Romania, Bulgaria, Turkey, Russia, Pakistan, Malaysia, and Israel

$table = $installer->getConnection()
    ->newTable($installer->getTable('dfraudintegration/scores'))
	 ->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'unsigned' => true,
        'identity' => true,
        'nullable' => false,
        'primary'  => true,
    ), 'Entity id')
    ->addColumn('field', Varien_Db_Ddl_Table::TYPE_TEXT, 200, array(
        'nullable' => true,
    ), 'Field')
    ->addColumn('score', Varien_Db_Ddl_Table::TYPE_TEXT, 63, array(
        'nullable' => true,
        'default'  => null,
    ), 'Score')
	 ->addColumn('description', Varien_Db_Ddl_Table::TYPE_TEXT, 200, array(
        'nullable' => true,
        'default'  => null,
    ), 'Description')
	->addColumn('risk', Varien_Db_Ddl_Table::TYPE_TEXT, 10, array(
        'nullable' => true,
        'default'  => null,
    ), 'Risk')
	->addIndex($installer->getIdxName(
            $installer->getTable('dfraudintegration/scores'),
            array('id'),
            Varien_Db_Adapter_Interface::INDEX_TYPE_INDEX
        ),
        array('id'),
        array('type' => Varien_Db_Adapter_Interface::INDEX_TYPE_INDEX)
    )
	
    ->setComment('Dfraud Scores');

$installer->getConnection()->createTable($table);

$installer->startSetup();

$installer->run("
				insert  into {$this->getTable('dfraud_score')}
				(`field`,`score`, `description`,`risk`) 
				values 
				('address_bill_ship', '0.5', 'Billing and shipping address doesn''t match.', 'HIGH'),
				('zip_bill', '0.75', 'Billing address post code not found.', 'MEDIUM'),
				('dis_bill_ip', '0.75', 'Distance between billing and IP location exceeds maximum.', 'MEDIUM'),
				('ip_bill_city', '0.5', 'IP and Billing address differs (City).', 'MEDIUM'),
				('ip_bill_region', '0.75', 'IP and Billing address differs (Region).', 'MEDIUM'),
				('ip_bill_country_id', '1.00', 'IP and Billing address differs (Country)', 'MEDIUM'),
				('hr_bill', '1.25', 'Billing address in high risk country', 'HIGH'),
				('hr_ship', '1.00', 'Shipping address in high risk country', 'HIGH'),
				('hr_ip', '1.00', 'IP address in high risk country', 'HIGH'),
				('order_hist_fraud', '1.25', 'Previous Fraud orders from user exists.', 'HIGH'),
				('order_hist_count_ip_user', '0.50', 'Order count of User and IP doesn''t match', 'LOW'),
				('order_amount_avg', '0.75', 'Order amount is greater than average order amount', 'HIGH'),
				('bin_country', '1.50', 'CC issuing country and Billing address country doesn''t match', 'HIGH'),
				('order_hist_first_order', '0.25', 'First order from user', 'LOW'),
				('zip_ship', '0.5', 'Shipping address post code not found', 'MEDIUM');
				");

$installer->run("
				insert  into {$this->getTable('dfraud_highrisk_countries')}
				(`country_id`,`country`,`region`) 
				values ('UA',  'Ukraine', ''),
				('ID','Indonesia',''),('YG','Yugoslavia',''),('YG','Yugoslavia',''),
				('LT','Lithuania',''),('EG','Egypt',''),('RO','Romania','')
				,('BG','Bulgaria',''),('TR','Turkey',''),('RU','Russia',''),('PK','Pakistan',''),
				('MY','Malaysia',''),('IL','Israel','');
				"); 
$installer->endSetup();
?>