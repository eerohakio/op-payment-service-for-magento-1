<?php

$installer = $this;
$connection = $installer->getConnection();
$installer->startSetup();
$data = array(
    array('pending_opcheckout', 'Pending Op Checkout')
);
$connection = $installer->getConnection()->insertArray(
    $installer->getTable('sales/order_status'),
    array('status', 'label'),
    $data
);