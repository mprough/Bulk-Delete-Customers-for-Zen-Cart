<?php
/** Exercise the real page entry point without preloading the currency class. */
error_reporting(E_ALL);
set_error_handler(function ($s, $m, $f, $l) { throw new ErrorException($m, 0, $s, $f, $l); });
$root = dirname(__DIR__) . '/files/zc_plugins/BulkDeleteCustomers/v1.0.1';
$temp = sys_get_temp_dir() . '/bdc-bootstrap-' . bin2hex(random_bytes(8));
mkdir($temp . '/includes/classes', 0700, true);
$start = getcwd();
try {
    file_put_contents($temp . '/includes/application_top.php', <<<'FIXTURE'
<?php
// Minimal Zen Cart bootstrap: currencies is intentionally NOT preloaded.
define('IS_ADMIN_FLAG', true);
define('DIR_WS_INCLUDES', 'includes/');
define('DIR_WS_CLASSES', 'includes/classes/');
define('DIR_FS_CATALOG', getcwd() . '/');
define('CHARSET', 'utf-8');
define('HTML_PARAMS', 'lang="en"');
define('FILENAME_CUSTOMERS', 'customers');
define('FILENAME_BULK_DELETE_CUSTOMERS', 'bulk_delete_customers');
foreach (['CUSTOMERS', 'CUSTOMERS_INFO', 'ORDERS'] as $t) { define('TABLE_' . $t, strtolower($t)); }
$_SESSION = ['admin_id' => 1, 'securityToken' => 'fixture'];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST = [];
$_GET = [];
function zen_is_superuser() { return true; }
function zen_db_input($value) { return addslashes($value); }
function zen_href_link($page, $params = '') { return 'index.php?cmd=' . $page . ($params ? '&' . $params : ''); }
function zen_draw_form($name, $action, $params = '', $method = 'post', $attrs = '') { return '<form name="' . $name . '" method="' . $method . '" action="' . zen_href_link($action, $params) . '" ' . $attrs . '>'; }
$db = new class {
    public function Execute($sql) {
        return strpos($sql, 'SELECT COUNT(*) AS total') === 0 ? (object)['fields' => ['total' => 0]] : [];
    }
};
FIXTURE
    );
    file_put_contents($temp . '/includes/classes/currencies.php', '<?php class currencies { public function __construct() { $GLOBALS["bdc_currency_loaded"] = true; } }');
    foreach (['admin_html_head.php', 'header.php', 'footer.php', 'application_bottom.php'] as $f) { file_put_contents($temp . '/includes/' . $f, '<?php'); }
    foreach (require $root . '/admin/includes/languages/english/lang.bulk_delete_customers.php' as $key => $value) { define($key, $value); }
    chdir($temp);
    if (class_exists('currencies', false)) { throw new RuntimeException('Test must start with currency class unloaded'); }
    ob_start();
    require $root . '/admin/bulk_delete_customers.php';
    $html = ob_get_clean();
    if (empty($GLOBALS['bdc_currency_loaded']) || strpos($html, BDC_NO_RESULTS) === false || strpos($html, '</html>') === false) {
        throw new RuntimeException('Admin page bootstrap failed');
    }
    echo "Admin page bootstrap passed with currency class initially unloaded.\n";
} finally {
    chdir($start);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($temp);
}
