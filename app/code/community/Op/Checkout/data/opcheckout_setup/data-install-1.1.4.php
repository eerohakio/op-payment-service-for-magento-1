<?php

$installer = $this;
$connection = $installer->getConnection();
$installer->startSetup();
$data = array(
    array('pending_opcheckout', 'Pending Op Payment Service')
);
$connection = $installer->getConnection()->insertArray(
    $installer->getTable('sales/order_status'),
    array('status', 'label'),
    $data
);