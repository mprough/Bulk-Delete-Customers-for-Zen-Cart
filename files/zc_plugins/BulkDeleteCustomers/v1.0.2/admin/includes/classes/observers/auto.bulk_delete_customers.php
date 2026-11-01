<?php
/** Add selection controls to the native Customers listing without overwrites. */
if (!defined('IS_ADMIN_FLAG') || IS_ADMIN_FLAG !== true) { die('Illegal Access'); }
class zcObserverBulkDeleteCustomers extends base
{
    public function __construct()
    {
        if (empty($_SESSION['admin_id']) || (!zen_is_superuser() && (!check_page(FILENAME_BULK_DELETE_CUSTOMERS, []) || !check_page(FILENAME_CUSTOMERS, [])))) {
            return;
        }
        if (!class_exists('BulkDeleteCustomers', false)) { require_once dirname(__DIR__) . '/BulkDeleteCustomers.php'; }
        BulkDeleteCustomers::englishDefaults(dirname(__DIR__, 2) . '/languages/english/extra_definitions/lang.bulk_delete_customers_menu.php');
        $this->attach($this, ['NOTIFY_ADMIN_CUSTOMERS_LISTING_HEADER', 'NOTIFY_ADMIN_CUSTOMERS_LISTING_ELEMENT']);
    }
    public function update(&$class, $eventID, $paramsArray = [], &$columns = null)
    {
        if ($eventID === 'NOTIFY_ADMIN_CUSTOMERS_LISTING_HEADER') {
            $content = zen_draw_form('bdc_native', FILENAME_BULK_DELETE_CUSTOMERS, '', 'post', 'id="bdc_native"');
            $content .= '<input type="hidden" name="action" value="review">';
            $content .= '<label><input type="checkbox" id="bdc_native_all" aria-label="' . zen_output_string_protected(BDC_LIST_SELECT_ALL) . '"> ' . BDC_LIST_SELECT_ALL . '</label><br>';
            $content .= '<button type="submit" class="btn btn-sm btn-danger">' . BDC_LIST_REVIEW . '</button><br>';
            $content .= '<small>' . BDC_LIST_LIMIT . '</small><br>';
            $content .= '<a href="' . zen_href_link(FILENAME_BULK_DELETE_CUSTOMERS) . '">' . BDC_LIST_FILTERS . '</a></form>';
            $content .= <<<'JS'
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('bdc_native');
    var all = document.getElementById('bdc_native_all');
    var boxes = Array.from(document.querySelectorAll('.bdc-native-customer'));
    all.addEventListener('change', function () {
        boxes.forEach(function (box, index) { box.checked = all.checked && index < 50; });
        sync();
    });
    function sync() {
        var count = boxes.filter(function (box) { return box.checked; }).length;
        all.checked = count > 0 && count === Math.min(boxes.length, 50);
        all.indeterminate = count > 0 && !all.checked;
        form.querySelector('button').disabled = count === 0 || count > 50;
    }
    boxes.forEach(function (box) { box.addEventListener('change', sync); });
    sync();
});
</script>
JS;
            $columns[] = ['content' => $content, 'class' => 'text-center', 'parms' => 'onclick="event.stopPropagation()"'];
        } else {
            $id = (int)($paramsArray['customers_id'] ?? 0);
            $columns[] = ['content' => '<input type="checkbox" class="bdc-native-customer" style="width:20px;height:20px" form="bdc_native" name="customer_ids[]" value="' . $id . '" aria-label="' . zen_output_string_protected(BDC_LIST_SELECT . ' ' . $id) . '">', 'class' => 'text-center', 'parms' => 'onclick="event.stopPropagation()"'];
        }
    }
}
