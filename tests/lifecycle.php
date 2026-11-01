<?php
namespace Zencart\PluginSupport {
    class ScriptedInstaller {
        public array $sql = [];
        protected function executeInstallerSql($sql) { $this->sql[] = $sql; }
        public function install() { return $this->executeInstall(); }
        public function upgrade() { return $this->executeUpgrade('0.0.0'); }
        public function uninstall() { return $this->executeUninstall(); }
    }
}
namespace {
    error_reporting(E_ALL);
    set_error_handler(function ($s, $m, $f, $l) { throw new \ErrorException($m, 0, $s, $f, $l); });
    define('IS_ADMIN_FLAG', true);
    define('TABLE_CONFIGURATION', 'test_configuration');
    define('TABLE_ADMIN_PAGES_TO_PROFILES', 'test_admin_pages_to_profiles');
    $root = dirname(__DIR__) . '/files/zc_plugins/BulkDeleteCustomers/v1.0.2';
    $pages = [];
    $registrations = 0;
    function check($condition, $message) { if (!$condition) { throw new \RuntimeException($message); } }
    function zen_page_key_exists($key) { global $pages; return isset($pages[$key]); }
    function zen_register_admin_page(...$args) { global $pages, $registrations; $pages[$args[0]] = $args; ++$registrations; }
    function zen_deregister_admin_pages($keys) { global $pages; foreach ($keys as $key) { unset($pages[$key]); } }
    require $root . '/Installer/ScriptedInstaller.php';
    $installer = new \ScriptedInstaller;
    check($installer->install(), 'Install');
    check($pages['bulkDeleteCustomers'][4] === 'customers', 'Customers menu');
    check(defined($pages['bulkDeleteCustomers'][1]) && defined($pages['bulkDeleteCustomers'][2]), 'Language and filename defined at installation');
    $pages['bulkDeleteCustomers'][] = 'existing profile permissions';
    $saved = $pages['bulkDeleteCustomers'];
    check($installer->upgrade(), 'Upgrade');
    check($registrations === 1 && $pages['bulkDeleteCustomers'] === $saved, 'Idempotent installation preserves registration');
    $db = new class {
        public bool $installed = true;
        public function Execute($sql) { return (object)['EOF' => !$this->installed]; }
    };
    include $root . '/admin/includes/functions/extra_functions/bulk_delete_customers_menu.php';
    check($registrations === 1, 'Runtime does not rewrite existing menu');
    unset($pages['bulkDeleteCustomers']);
    include $root . '/admin/includes/functions/extra_functions/bulk_delete_customers_menu.php';
    check($registrations === 2 && isset($pages['bulkDeleteCustomers']), 'Missing menu repaired');
    check($installer->uninstall() && !isset($pages['bulkDeleteCustomers']), 'Uninstall removes registration');
    check(end($installer->sql) === "DELETE FROM test_configuration WHERE configuration_key = 'PLUGIN_BULK_DELETE_CUSTOMERS_VERSION'", 'Uninstall deletes exact owned marker');
    foreach ($installer->sql as $sql) { check(stripos($sql, 'test_customers') === false && stripos($sql, 'orders') === false, 'Installer never mutates business data'); }
    $db->installed = false;
    include $root . '/admin/includes/functions/extra_functions/bulk_delete_customers_menu.php';
    check(!isset($pages['bulkDeleteCustomers']), 'Uninstalled plugin does not repair menu');
    echo "Lifecycle tests passed.\n";
}
