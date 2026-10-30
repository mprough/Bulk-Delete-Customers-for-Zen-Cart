<?php
/** Behavioral tests: selection, review binding, replay, filters and native hooks. */
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
define('IS_ADMIN_FLAG', true);
define('FILENAME_CUSTOMERS', 'customers');
define('FILENAME_BULK_DELETE_CUSTOMERS', 'bulk_delete_customers');
foreach (['CUSTOMERS', 'CUSTOMERS_INFO', 'ORDERS', 'CONFIGURATION'] as $table) { define('TABLE_' . $table, 'test_' . strtolower($table)); }
$root = dirname(__DIR__) . '/files/zc_plugins/BulkDeleteCustomers/v1.0.0';
require $root . '/admin/includes/classes/BulkDeleteCustomers.php';
function check($truth, $message) { if (!$truth) { throw new RuntimeException($message); } }
function fails(callable $fn, string $message) { try { $fn(); } catch (InvalidArgumentException $e) { check($e->getMessage() === $message, 'Unexpected error'); return; } throw new RuntimeException('Expected rejection: ' . $message); }
function zen_db_input($s) { return addslashes($s); }
function row(int $id, int $orders = 0): array { return ['customers_id' => $id, 'customers_firstname' => 'Test', 'customers_lastname' => 'Customer', 'customers_email_address' => "test$id@example.test", 'order_count' => $orders]; }
check(BulkDeleteCustomers::ids(['2', '2', 3]) === [2, 3], 'Deduplicate selected IDs');
foreach ([[], '1', [0], [-1], ['1 OR 1=1'], [['1']], ['2147483648'], range(1, 51)] as $invalid) { fails(fn() => BulkDeleteCustomers::ids($invalid), 'BDC_ERROR_SELECTION'); }
check(count(BulkDeleteCustomers::ids(range(1, 50))) === 50, 'Accept limit');
fails(fn() => BulkDeleteCustomers::filters(['from' => '2026-02-30']), 'BDC_ERROR_DATES');
fails(fn() => BulkDeleteCustomers::filters(['from' => '2026-10-30', 'to' => '2026-01-01']), 'BDC_ERROR_DATES');
$f = BulkDeleteCustomers::filters(['q' => "%' OR 1=1_!", 'from' => '2024-02-29', 'to' => '2024-02-29', 'orders' => 'any']);
$sql = BulkDeleteCustomers::where($f);
check(strpos($sql, "!%\\' OR 1=1!_!!") !== false, 'Escape SQL and LIKE wildcards');
check(strpos($sql, "< '2024-03-01 00:00:00'") !== false, 'Inclusive end date');
check(strpos(BulkDeleteCustomers::where(BulkDeleteCustomers::filters([])), 'NOT EXISTS') !== false, 'No orders default');
foreach (['never', 'url', 'unapproved'] as $signal) { check(strpos(BulkDeleteCustomers::where(BulkDeleteCustomers::filters(['signal' => $signal])), ['never' => 'number_of_logons', 'url' => 'REGEXP', 'unapproved' => 'customers_authorization'][$signal]) !== false, 'Account filters'); }
$pending = BulkDeleteCustomers::stage([1 => row(1)], 7, false, true);
$session = ['bulk_delete_customers_pending' => $pending];
fails(function () use (&$session, $pending) { BulkDeleteCustomers::consume($session, $pending['token'], 8); }, 'BDC_ERROR_EXPIRED');
fails(function () use (&$session) { BulkDeleteCustomers::consume($session, 'forged', 7); }, 'BDC_ERROR_EXPIRED');
check(BulkDeleteCustomers::consume($session, $pending['token'], 7) === $pending, 'Consume approved selection');
fails(function () use (&$session, $pending) { BulkDeleteCustomers::consume($session, $pending['token'], 7); }, 'BDC_ERROR_EXPIRED');
$pending['expires'] = time() - 1;
$session['bulk_delete_customers_pending'] = $pending;
fails(function () use (&$session, $pending) { BulkDeleteCustomers::consume($session, $pending['token'], 7); }, 'BDC_ERROR_EXPIRED');
class FakeDb {
    public array $rows;
    public function Execute($sql) { preg_match('/IN \((\d+)\)/', $sql, $m); return isset($this->rows[(int)$m[1]]) ? [$this->rows[(int)$m[1]]] : []; }
}
class Customer {
    public static array $calls = [];
    private int $id;
    public function __construct($id) { $this->id = $id; }
    public function delete($reviews, $forget) { self::$calls[] = [$this->id, $reviews, $forget]; }
}
class FakeNotifier {
    public array $calls = [];
    public function notify($event, $data) { $this->calls[] = [$event, $data]; }
}
$db = new FakeDb;
$db->rows = [1 => row(1), 2 => row(2, 1), 3 => row(3)];
$snapshot = [1 => row(1), 2 => row(2), 3 => row(3), 4 => row(4)];
$db->rows[3]['customers_email_address'] = 'changed@example.test';
$pending = BulkDeleteCustomers::stage($snapshot, 7, false, false);
$notifier = new FakeNotifier;
check(BulkDeleteCustomers::execute($db, $notifier, $pending) === ['deleted' => 1, 'skipped' => 3], 'Skip new orders, changed identity and missing rows');
check(Customer::$calls === [[1, false, false]], 'Only approved account deleted, keep reviews, full removal');
check($notifier->calls[0] === ['NOTIFIER_ADMIN_ZEN_CUSTOMERS_DELETE_CONFIRM', ['customers_id' => 1]], 'Core pre-delete hook');
Customer::$calls = [];
$pending = BulkDeleteCustomers::stage([2 => row(2, 1)], 7, true, true);
check(BulkDeleteCustomers::execute($db, $notifier, $pending)['deleted'] === 1, 'Explicitly permit account with orders');
check(Customer::$calls === [[2, true, false]], 'Review removal option passed to core');
// Exercise native listing integration as Zen Cart does, without an HTML rewrite.
class base { public array $events = []; public function attach($observer, $events) { $this->events = $events; } }
$allowed = true;
function zen_is_superuser() { return false; }
function check_page($page, $params) { global $allowed; return $allowed; }
function zen_output_string_protected($s) { return htmlspecialchars($s, ENT_QUOTES); }
function zen_href_link($page) { return 'index.php?cmd=' . $page; }
function zen_draw_form($name, $action, $params = '', $method = 'post', $attrs = '') { return '<form method="post" action="' . zen_href_link($action) . '" ' . $attrs . '><input type="hidden" name="securityToken" value="test">'; }
foreach (require $root . '/admin/includes/languages/english/extra_definitions/lang.bulk_delete_customers_menu.php' as $k => $v) { define($k, $v); }
require $root . '/admin/includes/classes/observers/auto.bulk_delete_customers.php';
$_SESSION = ['admin_id' => 7];
$observer = new zcObserverBulkDeleteCustomers;
check(count($observer->events) === 2, 'Attach native listing hooks');
$columns = [['content' => 'another plugin']];
$observer->update($observer, 'NOTIFY_ADMIN_CUSTOMERS_LISTING_HEADER', [], $columns);
check(count($columns) === 2 && strpos($columns[1]['content'], 'id="bdc_native"') !== false, 'Preserve other plugin columns and add review form');
check(strpos($columns[1]['content'], 'securityToken') !== false, 'Native form has CSRF token');
$columns = [];
$observer->update($observer, 'NOTIFY_ADMIN_CUSTOMERS_LISTING_ELEMENT', ['customers_id' => 23], $columns);
check(strpos($columns[0]['content'], 'form="bdc_native"') !== false && strpos($columns[0]['content'], 'value="23"') !== false, 'Row checkbox associated with review form');
check(strpos($columns[0]['parms'], 'stopPropagation') !== false, 'Checkbox does not trigger row navigation');
$allowed = false;
check((new zcObserverBulkDeleteCustomers)->events === [], 'Hide controls without page permission');
echo "Runtime tests passed.\n";
