<?php

/**
 *
 * @category MultiSafepay
 * @package  MultiSafepay_Msp
 */
/** @var $this MultiSafepay_Msp_Model_Setup */
$this->startSetup();

/** @var $conn Varien_Db_Adapter_Pdo_Mysql */
$conn = $this->getConnection();

$additionalColumns = array(
    $this->getTable('sales/order') => array(
        'servicecost',
        'base_servicecost',
        'servicecost_invoiced',
        'base_servicecost_invoiced',
        'servicecost_tax',
        'base_servicecost_tax',
        'servicecost_tax_invoiced',
        'base_servicecost_tax_invoiced',
        'servicecost_refunded',
        'base_servicecost_refunded',
        'servicecost_tax_refunded',
        'base_servicecost_tax_refunded',
        'servicecost_pdf',
    ),
    $this->getTable('sales/invoice') => array(
        'servicecost',
        'base_servicecost',
        'servicecost_invoiced',
        'base_servicecost_invoiced',
        'servicecost_tax',
        'base_servicecost_tax',
        'servicecost_tax_invoiced',
        'base_servicecost_tax_invoiced',
        'servicecost_refunded',
        'base_servicecost_refunded',
        'servicecost_tax_refunded',
        'base_servicecost_tax_refunded',
        'servicecost_pdf',
    ),
    $this->getTable('sales/quote') => array(
        'servicecost',
        'base_servicecost',
        'servicecost_invoiced',
        'base_servicecost_invoiced',
        'servicecost_tax',
        'base_servicecost_tax',
        'servicecost_tax_invoiced',
        'base_servicecost_tax_invoiced',
        'servicecost_refunded',
        'base_servicecost_refunded',
        'servicecost_tax_refunded',
        'base_servicecost_tax_refunded',
        'servicecost_pdf',
    ),
    $this->getTable('sales/creditmemo') => array(
        'servicecost',
        'base_servicecost',
        'servicecost_invoiced',
        'base_servicecost_invoiced',
        'servicecost_tax',
        'base_servicecost_tax',
        'servicecost_tax_invoiced',
        'base_servicecost_tax_invoiced',
        'servicecost_refunded',
        'base_servicecost_refunded',
        'servicecost_tax_refunded',
        'base_servicecost_tax_refunded',
        'servicecost_pdf',
    ),
);

foreach ($additionalColumns as $table => $columns) {
    foreach ($columns as $column) {
        $conn->addColumn($table, $column, array(
            'type' => Varien_Db_Ddl_Table::TYPE_DECIMAL,
            'precision' => 12,
            'scale' => 4,
            'nullable' => true,
            'default' => null,
            'comment' => ucwords(str_replace('_', ' ', $column)),
        ));
    }
}

$this->endSetup();


/*
 * 	Qwindo, add attribute to category
 */
/*
$installer = $this;
$installer->startSetup();

$entityTypeId = $installer->getEntityTypeId('catalog_category');
$attributeSetId = $installer->getDefaultAttributeSetId($entityTypeId);
$attributeGroupId = $installer->getDefaultAttributeGroupId($entityTypeId, $attributeSetId);

$installer->addAttribute('catalog_category', 'msp_cashback', array(
    'type' => 'int',
    'label' => 'Qwindo Cashback',
    'input' => 'text',
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
    'visible' => true,
    'required' => false,
    'user_defined' => false,
    'default' => 0
));


$installer->addAttributeToGroup(
        $entityTypeId, $attributeSetId, $attributeGroupId, 'msp_cashback', '11'                    //last Magento's attribute position in General tab is 10
);

$attributeId = $installer->getAttributeId($entityTypeId, 'msp_cashback');

$installer->run("
REPLACE INTO `{$installer->getTable('catalog_category_entity_int')}`
(`entity_type_id`, `attribute_id`, `entity_id`, `value`)
    SELECT '{$entityTypeId}', '{$attributeId}', `entity_id`, '0'
        FROM `{$installer->getTable('catalog_category_entity')}`;
");


//this will set data of your custom attribute for root category
Mage::getModel('catalog/category')
        ->load(1)
        ->setImportedCatId(0)
        ->setInitialSetupFlag(true)
        ->save();

//this will set data of your custom attribute for default category
Mage::getModel('catalog/category')
        ->load(2)
        ->setImportedCatId(0)
        ->setInitialSetupFlag(true)
        ->save();


$installer->addAttribute('catalog_product', 'msp_cashback', array(
    'type' => 'int',
    'group' => 'General',
    'backend' => '',
    'frontend' => '',
    'label' => 'Qwindo Cashback',
    'input' => 'text',
    'class' => '',
    'source' => '',
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
    'visible' => true,
    'required' => false,
    'user_defined' => false,
    'default' => '0',
    'searchable' => true,
    'filterable' => false,
    'comparable' => false,
    'visible_on_front' => false,
    'unique' => false,
    'apply_to' => '',
    'is_configurable' => true
));



$installer->endSetup();
*/



