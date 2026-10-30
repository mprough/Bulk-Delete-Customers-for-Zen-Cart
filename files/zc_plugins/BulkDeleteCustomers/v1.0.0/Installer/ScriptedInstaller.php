<?php
if (!defined('IS_ADMIN_FLAG')) { die('Illegal Access'); }
use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallBase;
class ScriptedInstaller extends ScriptedInstallBase
{
    public string $pluginKey = 'BulkDeleteCustomers';
    public string $version = '1.0.0';
    protected function executeInstall(): bool
    {
        // Hidden installation marker, no editable configuration or new tables.
        $this->executeInstallerSql("INSERT IGNORE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order) VALUES ('Bulk Delete Customers version', 'PLUGIN_BULK_DELETE_CUSTOMERS_VERSION', '1.0.0', 'Managed by Plugin Manager.', 0, 0)");
        $this->executeInstallerSql("UPDATE " . TABLE_CONFIGURATION . " SET configuration_value = '1.0.0', configuration_group_id = 0 WHERE configuration_key = 'PLUGIN_BULK_DELETE_CUSTOMERS_VERSION'");
        if (!defined('FILENAME_BULK_DELETE_CUSTOMERS')) { define('FILENAME_BULK_DELETE_CUSTOMERS', 'bulk_delete_customers'); }
        if (!defined('BOX_CUSTOMERS_BULK_DELETE_CUSTOMERS')) { define('BOX_CUSTOMERS_BULK_DELETE_CUSTOMERS', 'Bulk customer removal'); }
        if (!zen_page_key_exists('bulkDeleteCustomers')) {
            zen_register_admin_page('bulkDeleteCustomers', 'BOX_CUSTOMERS_BULK_DELETE_CUSTOMERS', 'FILENAME_BULK_DELETE_CUSTOMERS', '', 'customers', 'Y', 100);
        }
        return true;
    }
    protected function executeUpgrade(...$args): bool { return $this->executeInstall(); }
    protected function executeUninstall(): bool
    {
        zen_deregister_admin_pages(['bulkDeleteCustomers']);
        $this->executeInstallerSql("DELETE FROM " . TABLE_ADMIN_PAGES_TO_PROFILES . " WHERE page_key = 'bulkDeleteCustomers'");
        $this->executeInstallerSql("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'PLUGIN_BULK_DELETE_CUSTOMERS_VERSION'");
        return true;
    }
}
