<?php
/** Disposable MySQL/MariaDB only. BDC_TEST_DSN must name bdc_plugin_test. */
error_reporting(E_ALL);
set_error_handler(function ($s, $m, $f, $l) { throw new ErrorException($m, 0, $s, $f, $l); });
$zen = getenv('ZEN_ROOT');
$dsn = getenv('BDC_TEST_DSN');
if (!$zen || !$dsn || !preg_match('/dbname=bdc_plugin_test(?:;|$)/', $dsn)) { throw new RuntimeException('Requires disposable bdc_plugin_test database and ZEN_ROOT'); }
define('IS_ADMIN_FLAG', true);
define('FILENAME_CUSTOMERS', 'customers');
$_SESSION = ['admin_id' => 1];
require $zen . '/includes/database_tables.php';
foreach (['Singleton', 'NotifierManager', 'ObserverManager'] as $trait) { require $zen . '/includes/classes/traits/' . $trait . '.php'; }
require $zen . '/includes/classes/EventDto.php';
require $zen . '/includes/classes/class.base.php';
// Adapter implements the DB contract while letting SQL run against a real database.
class queryFactoryResult implements IteratorAggregate {
    public bool $EOF;
    public array $fields;
    private array $rows;
    public function __construct(array $rows) { $this->rows = $rows; $this->EOF = !$rows; $this->fields = $rows[0] ?? []; }
    public function getIterator(): Traversable { return new ArrayIterator($this->rows); }
    public function RecordCount() { return count($this->rows); }
}
class queryFactory {
    public PDO $pdo;
    public bool $dieOnErrors = true;
    public int $error_number = 0;
    public string $error_text = '';
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }
    public function Execute($sql, ...$args) { $q = $this->pdo->query($sql); return new queryFactoryResult($q->columnCount() ? $q->fetchAll(PDO::FETCH_ASSOC) : []); }
    public function ExecuteNoCache($sql, ...$args) { return $this->Execute($sql); }
    public function bindVars($sql, $key, $value, $type) { return str_replace($key, $type === 'integer' ? (string)(int)$value : $this->pdo->quote((string)$value), $sql); }
}
$pdo = new PDO($dsn, getenv('BDC_TEST_USER') ?: 'root', getenv('BDC_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
check(!$pdo->query('SHOW TABLES')->fetch(), 'Test database must be empty');
$pdo->exec("SET SESSION sql_mode = ''");
$pdo->exec(file_get_contents($zen . '/zc_install/sql/install/mysql_zencart.sql'));
$db = new queryFactory($pdo);
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function zen_db_input($text) { global $pdo; return substr($pdo->quote($text), 1, -1); }
function zen_record_admin_activity(...$args) { $GLOBALS['activity'][] = $args; }
function zen_define_default($name, $value) { if (!defined($name)) { define($name, $value); } }
require $zen . '/admin/includes/functions/admin_access.php';
require $zen . '/includes/classes/PluginSupport/ScriptedInstallHelpers.php';
require $zen . '/includes/classes/PluginSupport/ScriptedInstaller.php';
require $zen . '/includes/classes/PluginSupport/PluginErrorContainer.php';
$root = dirname(__DIR__) . '/files/zc_plugins/BulkDeleteCustomers/v1.0.1';
require $root . '/Installer/ScriptedInstaller.php';
$installer = new ScriptedInstaller($db, new Zencart\PluginSupport\PluginErrorContainer);
function lifecycle($installer, $method, $args = []) { $m = new ReflectionMethod($installer, $method); $m->setAccessible(true); return $m->invokeArgs($installer, $args); }
check(lifecycle($installer, 'executeInstall'), 'Real install');
check(zen_page_key_exists('bulkDeleteCustomers'), 'Menu created');
$pdo->exec("INSERT INTO admin_pages_to_profiles (page_key, profile_id) VALUES ('bulkDeleteCustomers', 2)");
check(lifecycle($installer, 'executeUpgrade', ['0.0.0']), 'Idempotent install/upgrade');
check((int)$pdo->query("SELECT COUNT(*) FROM admin_pages_to_profiles WHERE page_key='bulkDeleteCustomers'")->fetchColumn() === 1, 'Permissions preserved on upgrade');
$pdo->exec("DELETE FROM admin_pages WHERE page_key='bulkDeleteCustomers'");
include $root . '/admin/includes/functions/extra_functions/bulk_delete_customers_menu.php';
check(zen_page_key_exists('bulkDeleteCustomers'), 'Real runtime menu repair');
require $root . '/admin/includes/classes/BulkDeleteCustomers.php';
require $zen . '/includes/classes/Customer.php';
class currencies { public function format(...$args) { return '0.00'; } }
$currencies = new currencies;
$zco_notifier = new base;
for ($id = 1; $id <= 3; ++$id) {
    $pdo->exec("INSERT INTO customers (customers_id, customers_firstname, customers_lastname, customers_email_address, registration_ip) VALUES ($id, 'Test', 'Customer', 'test$id@example.test', '192.0.2.1')");
    $pdo->exec("INSERT INTO customers_info (customers_info_id, customers_info_date_account_created, customers_info_number_of_logons) VALUES ($id, '2026-10-30 23:59:59', 0)");
    $pdo->exec("INSERT INTO address_book (customers_id) VALUES ($id)");
    $pdo->exec("INSERT INTO customers_basket (customers_id, products_id, customers_basket_quantity) VALUES ($id, '1', 1)");
    $pdo->exec("INSERT INTO reviews (reviews_id, products_id, customers_id, customers_name, reviews_rating) VALUES ($id, 1, $id, 'Test Customer', 5)");
    $pdo->exec("INSERT INTO reviews_description (reviews_id, languages_id, reviews_text) VALUES ($id, 1, 'Test review')");
}
$pdo->exec("INSERT INTO orders (customers_id, currency, currency_value) VALUES (2, 'USD', 1)");
$f = BulkDeleteCustomers::filters(['to' => '2026-10-30', 'ip' => '192.0.2', 'q' => '@example.test']);
check($db->Execute('SELECT ' . BulkDeleteCustomers::columns() . ' FROM ' . BulkDeleteCustomers::source() . ' WHERE ' . BulkDeleteCustomers::where($f))->RecordCount() === 2, 'Real date/IP/email and order filters');
$pending = BulkDeleteCustomers::stage(BulkDeleteCustomers::rows($db, [1, 2]), 1, false, false);
check(BulkDeleteCustomers::execute($db, $zco_notifier, $pending) === ['deleted' => 1, 'skipped' => 1], 'Core deletion with order protection');
check((int)$pdo->query('SELECT COUNT(*) FROM address_book WHERE customers_id=1')->fetchColumn() === 0, 'Address cleanup');
check((int)$pdo->query('SELECT COUNT(*) FROM customers_basket WHERE customers_id=1')->fetchColumn() === 0, 'Basket cleanup');
check($pdo->query('SELECT customers_id FROM reviews WHERE reviews_id=1')->fetchColumn() === null, 'Keep review, unlink customer');
$pending = BulkDeleteCustomers::stage(BulkDeleteCustomers::rows($db, [2, 3]), 1, true, true);
check(BulkDeleteCustomers::execute($db, $zco_notifier, $pending)['deleted'] === 2, 'Delete accounts with orders on explicit override');
check((int)$pdo->query('SELECT COUNT(*) FROM orders WHERE customers_id=2')->fetchColumn() === 1, 'Order retained');
check((int)$pdo->query('SELECT COUNT(*) FROM reviews_description WHERE reviews_id IN (2,3)')->fetchColumn() === 0, 'Review text cleanup');
check(lifecycle($installer, 'executeUninstall'), 'Real uninstall');
check(!zen_page_key_exists('bulkDeleteCustomers'), 'Menu removed');
check((int)$pdo->query("SELECT COUNT(*) FROM configuration WHERE configuration_key='PLUGIN_BULK_DELETE_CUSTOMERS_VERSION'")->fetchColumn() === 0, 'Marker removed');
check((int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn() === 1, 'Uninstall retains orders');
echo "Real Zen Cart and database integration passed.\n";
