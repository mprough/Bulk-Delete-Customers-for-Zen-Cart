<?php
if (!defined('IS_ADMIN_FLAG') || IS_ADMIN_FLAG !== true) { die('Illegal Access'); }
if (!defined('FILENAME_BULK_DELETE_CUSTOMERS')) {
    define('FILENAME_BULK_DELETE_CUSTOMERS', 'bulk_delete_customers');
}
if (!defined('BOX_CUSTOMERS_BULK_DELETE_CUSTOMERS')) {
    define('BOX_CUSTOMERS_BULK_DELETE_CUSTOMERS', 'Bulk customer removal');
}
$bulkCustomersInstalled = defined('PLUGIN_BULK_DELETE_CUSTOMERS_VERSION');
if (!$bulkCustomersInstalled && isset($db)) {
    $bulkCustomersVersion = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'PLUGIN_BULK_DELETE_CUSTOMERS_VERSION' LIMIT 1");
    $bulkCustomersInstalled = !$bulkCustomersVersion->EOF;
}
if ($bulkCustomersInstalled && function_exists('zen_register_admin_page') && !zen_page_key_exists('bulkDeleteCustomers')) {
    zen_register_admin_page('bulkDeleteCustomers', 'BOX_CUSTOMERS_BULK_DELETE_CUSTOMERS', 'FILENAME_BULK_DELETE_CUSTOMERS', '', 'customers', 'Y', 100);
}
unset($bulkCustomersInstalled, $bulkCustomersVersion);
