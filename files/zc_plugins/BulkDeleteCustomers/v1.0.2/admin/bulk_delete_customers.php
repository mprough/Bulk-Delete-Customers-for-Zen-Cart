<?php
/** Copyright 2026 Melanie Prough, PRO-Webs, Inc. GPL-2.0-only. */
require 'includes/application_top.php';
require_once __DIR__ . '/includes/classes/BulkDeleteCustomers.php';
// Zen Cart checks this page's permission during application_top. Also require
// Customers access, so this feature cannot grant broader customer privileges.
if (empty($_SESSION['admin_id']) || (!zen_is_superuser() && (!check_page(FILENAME_CUSTOMERS, []) || !check_page(FILENAME_BULK_DELETE_CUSTOMERS, [])))) {
    zen_redirect(zen_href_link(FILENAME_DENIED));
}
function bdcEscape($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, CHARSET);
}
function bdcMessage(string $key, string $type = 'error'): void {
    global $messageStack;
    $messageStack->add_session(defined($key) ? constant($key) : $key, $type);
    zen_redirect(zen_href_link(FILENAME_BULK_DELETE_CUSTOMERS));
    exit;
}
// Custom admin pages do not automatically load the currency class.
require_once DIR_FS_CATALOG . DIR_WS_CLASSES . 'currencies.php';
$currencies = new currencies();
$pending = null;
$protected = 0;
$action = BulkDeleteCustomers::text($_POST['action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = BulkDeleteCustomers::text($_POST['securityToken'] ?? '');
    if ($token === '' || !hash_equals((string)($_SESSION['securityToken'] ?? ''), $token)) {
        bdcMessage('BDC_ERROR_TOKEN');
    }
    try {
        if ($action === 'cancel') {
            unset($_SESSION['bulk_delete_customers_pending']);
            zen_redirect(zen_href_link(FILENAME_BULK_DELETE_CUSTOMERS));
            exit;
        }
        if ($action === 'review') {
            unset($_SESSION['bulk_delete_customers_pending']);
            $ids = BulkDeleteCustomers::ids($_POST['customer_ids'] ?? []);
            $rows = BulkDeleteCustomers::rows($db, $ids);
            $allowOrders = ($_POST['allow_orders'] ?? '') === '1';
            if (!$allowOrders) {
                foreach ($rows as $id => $row) {
                    if ((int)$row['order_count'] > 0) { unset($rows[$id]); ++$protected; }
                }
            }
            if (!$rows) { bdcMessage('BDC_ERROR_EMPTY'); }
            $pending = BulkDeleteCustomers::stage($rows, (int)$_SESSION['admin_id'], $allowOrders, ($_POST['delete_reviews'] ?? '') === '1');
            $_SESSION['bulk_delete_customers_pending'] = $pending;
        } elseif ($action === 'delete') {
            if (($_POST['acknowledge'] ?? '') !== '1') { bdcMessage('BDC_ERROR_ACK'); }
            $pending = BulkDeleteCustomers::consume($_SESSION, BulkDeleteCustomers::text($_POST['confirmation'] ?? ''), (int)$_SESSION['admin_id']);
            try {
                $counts = BulkDeleteCustomers::execute($db, $zco_notifier, $pending);
            } catch (Throwable $e) {
                zen_record_admin_activity('Bulk customer removal stopped: ' . get_class($e) . '. Review error logs and remaining customers.', 'warning');
                error_log('Bulk Delete Customers: ' . $e->getMessage());
                bdcMessage('BDC_ERROR_FAILURE');
            }
            zen_record_admin_activity('Bulk customer removal: ' . $counts['deleted'] . ' removed, ' . $counts['skipped'] . ' skipped.', 'warning');
            $messageStack->add_session(sprintf(BDC_DONE, $counts['deleted'], $counts['skipped']), 'success');
            zen_redirect(zen_href_link(FILENAME_BULK_DELETE_CUSTOMERS));
            exit;
        } else { bdcMessage('BDC_ERROR_TOKEN'); }
    } catch (InvalidArgumentException $e) { bdcMessage($e->getMessage()); }
}
try { $filters = BulkDeleteCustomers::filters($_GET); }
catch (InvalidArgumentException $e) { bdcMessage($e->getMessage()); }
if ($pending === null) {
    $where = BulkDeleteCustomers::where($filters);
    $total = (int)$db->Execute('SELECT COUNT(*) AS total FROM ' . BulkDeleteCustomers::source() . ' WHERE ' . $where)->fields['total'];
    $pages = max(1, (int)ceil($total / BulkDeleteCustomers::LIMIT));
    $filters['page'] = min($filters['page'], $pages);
    $offset = ($filters['page'] - 1) * BulkDeleteCustomers::LIMIT;
    $sorts = ['newest' => 'ci.customers_info_date_account_created DESC, c.customers_id DESC', 'oldest' => 'ci.customers_info_date_account_created ASC, c.customers_id ASC', 'name' => 'c.customers_lastname, c.customers_firstname, c.customers_id'];
    $rows = $db->Execute('SELECT ' . BulkDeleteCustomers::columns() . ' FROM ' . BulkDeleteCustomers::source() . ' WHERE ' . $where . ' ORDER BY ' . $sorts[$filters['sort']] . ' LIMIT ' . BulkDeleteCustomers::LIMIT . ' OFFSET ' . $offset);
} else { $rows = $pending['rows']; }
?>
<!doctype html>
<html <?= HTML_PARAMS ?>>
<head>
<meta charset="<?= CHARSET ?>">
<title><?= bdcEscape(HEADING_TITLE) ?></title>
<link rel="stylesheet" href="includes/stylesheet.css">
<?php require DIR_WS_INCLUDES . 'admin_html_head.php'; ?>
<style>
.bdc-panel{background:#fff;border:1px solid #ddd;border-radius:6px;padding:18px;margin:15px 0}.bdc-filters{display:flex;flex-wrap:wrap;gap:14px;align-items:flex-end}.bdc-filters label{display:block}.bdc-filters input,.bdc-filters select{display:block;min-width:180px;padding:7px}.bdc-check{width:20px;height:20px;vertical-align:middle}.bdc-options label{display:block;margin:12px 0}.bdc-table td,.bdc-table th{vertical-align:middle!important}.bdc-actions{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin:15px 0}.bdc-table-wrap{overflow-x:auto}
</style>
</head>
<body>
<?php require DIR_WS_INCLUDES . 'header.php'; ?>
<main class="container-fluid">
<h1><?= bdcEscape($pending ? BDC_REVIEW_TITLE : HEADING_TITLE) ?></h1>
<p><?= bdcEscape($pending ? BDC_REVIEW_HELP : BDC_INTRO) ?></p>
<?php if ($pending === null): ?>
<p class="alert alert-info"><?= bdcEscape(BDC_HINT) ?></p>
<?= zen_draw_form('bdc_filters', FILENAME_BULK_DELETE_CUSTOMERS, '', 'get', 'class="bdc-panel bdc-filters"') ?>
<?php foreach (['q' => BDC_SEARCH, 'ip' => BDC_IP, 'from' => BDC_FROM, 'to' => BDC_TO] as $key => $label): ?>
<label><?= bdcEscape($label) ?><input type="<?= in_array($key, ['from', 'to'], true) ? 'date' : 'text' ?>" name="<?= $key ?>" value="<?= bdcEscape($filters[$key]) ?>" maxlength="150"></label>
<?php endforeach; ?>
<?php foreach (['orders' => [BDC_ORDERS, ['none' => BDC_NONE, 'any' => BDC_ANY, 'with' => BDC_WITH]], 'signal' => [BDC_SIGNAL, ['' => BDC_ALL_SIGNALS, 'never' => BDC_NEVER, 'url' => BDC_URL, 'unapproved' => BDC_UNAPPROVED]], 'sort' => [BDC_SORT, ['newest' => BDC_NEWEST, 'oldest' => BDC_OLDEST, 'name' => BDC_NAME]]] as $key => $field): ?>
<label><?= bdcEscape($field[0]) ?><select name="<?= $key ?>"><?php foreach ($field[1] as $value => $label): ?><option value="<?= $value ?>"<?= $filters[$key] === $value ? ' selected' : '' ?>><?= bdcEscape($label) ?></option><?php endforeach; ?></select></label>
<?php endforeach; ?>
<button type="submit" class="btn btn-primary"><?= bdcEscape(BDC_FILTER) ?></button>
<a href="<?= bdcEscape(zen_href_link(FILENAME_BULK_DELETE_CUSTOMERS)) ?>"><?= bdcEscape(BDC_RESET) ?></a>
</form>
<p><?= sprintf(BDC_RESULTS, $total, $filters['page'], $pages) ?></p>
<?php else: ?>
<p class="alert alert-danger"><?= sprintf(BDC_SELECTED, count($rows)) ?> <?= bdcEscape($pending['delete_reviews'] ? BDC_REVIEWS_DELETE : BDC_REVIEWS_KEEP) ?></p>
<?php if ($protected): ?><p class="alert alert-warning"><?= sprintf(BDC_PROTECTED, $protected) ?></p><?php endif; ?>
<?php endif; ?>
<?= zen_draw_form('bdc_selection', FILENAME_BULK_DELETE_CUSTOMERS) ?>
<input type="hidden" name="action" value="<?= $pending ? 'delete' : 'review' ?>">
<div class="bdc-table-wrap"><table class="table table-striped table-bordered bdc-table">
<thead><tr>
<?php if (!$pending): ?><th><input class="bdc-check" type="checkbox" id="bdc_all" aria-label="<?= bdcEscape(BDC_SELECT_ALL) ?>"></th><?php endif; ?>
<?php foreach ([BDC_ID, BDC_NAME, BDC_EMAIL, BDC_IP, BDC_CREATED, BDC_LAST_LOGIN, BDC_COUNT] as $label): ?><th><?= bdcEscape($label) ?></th><?php endforeach; ?>
</tr></thead><tbody>
<?php $shown = 0; foreach ($rows as $row): ++$shown; ?>
<tr>
<?php if (!$pending): ?><td><input type="checkbox" class="bdc-check bdc-customer" name="customer_ids[]" value="<?= (int)$row['customers_id'] ?>" aria-label="<?= bdcEscape(BDC_NAME . ' ' . $row['customers_id']) ?>"></td><?php endif; ?>
<td><?= (int)$row['customers_id'] ?></td><td><?= bdcEscape($row['customers_firstname'] . ' ' . $row['customers_lastname']) ?></td><td><?= bdcEscape($row['customers_email_address']) ?></td><td><?= bdcEscape($row['registration_ip']) ?></td><td><?= bdcEscape($row['created']) ?></td><td><?= bdcEscape($row['last_login']) ?></td><td><?= (int)$row['order_count'] ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$shown): ?><tr><td colspan="8"><?= bdcEscape(BDC_NO_RESULTS) ?></td></tr><?php endif; ?>
</tbody></table></div>
<div class="bdc-options">
<?php if ($pending): ?>
<input type="hidden" name="confirmation" value="<?= bdcEscape($pending['token']) ?>">
<label><input type="checkbox" name="acknowledge" value="1" required> <?= bdcEscape(BDC_ACK) ?></label>
<?php else: ?>
<p><?= bdcEscape(BDC_SELECTION_HELP) ?></p>
<label><input type="checkbox" name="allow_orders" value="1"> <?= bdcEscape(BDC_ALLOW_ORDERS) ?></label>
<label><input type="checkbox" name="delete_reviews" value="1"> <?= bdcEscape(BDC_DELETE_REVIEWS) ?></label>
<?php endif; ?>
</div>
<div class="bdc-actions"><button type="submit" class="btn <?= $pending ? 'btn-danger' : 'btn-primary' ?>"<?= !$shown ? ' disabled' : '' ?>><?= bdcEscape($pending ? BDC_CONFIRM : BDC_REVIEW) ?></button>
<?php if ($pending): ?><button type="submit" name="action" value="cancel" class="btn btn-default" formnovalidate><?= bdcEscape(BDC_CANCEL) ?></button><?php endif; ?>
<a href="<?= bdcEscape(zen_href_link(FILENAME_CUSTOMERS)) ?>"><?= bdcEscape(BDC_STANDARD) ?></a></div>
</form>
<?php if (!$pending): ?><nav class="bdc-actions" aria-label="Pagination">
<?php foreach ([-1 => BDC_PREVIOUS, 1 => BDC_NEXT] as $step => $label): $page = $filters['page'] + $step; if ($page < 1 || $page > $pages) { continue; } $params = $filters; $params['page'] = $page; ?>
<a class="btn btn-default" href="<?= bdcEscape(zen_href_link(FILENAME_BULK_DELETE_CUSTOMERS, http_build_query($params))) ?>"><?= bdcEscape($label) ?></a>
<?php endforeach; ?></nav><?php endif; ?>
</main>
<?php require DIR_WS_INCLUDES . 'footer.php'; ?>
<script>
document.getElementById('bdc_all')?.addEventListener('change', function () {
    document.querySelectorAll('.bdc-customer').forEach(function (box) { box.checked = this.checked; }, this);
});
document.querySelectorAll('.bdc-customer').forEach(function (box) {
    box.addEventListener('change', function () {
        var boxes = Array.from(document.querySelectorAll('.bdc-customer'));
        var count = boxes.filter(function (item) { return item.checked; }).length;
        var all = document.getElementById('bdc_all');
        all.checked = count === boxes.length;
        all.indeterminate = count > 0 && count < boxes.length;
    });
});
</script>
</body></html>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
