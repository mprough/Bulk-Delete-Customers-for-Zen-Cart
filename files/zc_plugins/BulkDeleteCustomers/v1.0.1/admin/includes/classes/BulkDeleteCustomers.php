<?php
/** Copyright 2026 Melanie Prough, PRO-Webs, Inc. GPL-2.0-only. */
if (!defined('IS_ADMIN_FLAG') || IS_ADMIN_FLAG !== true) { die('Illegal Access'); }
final class BulkDeleteCustomers
{
    public const LIMIT = 50;
    public const TTL = 900;

    public static function text($value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }

    public static function ids($values): array
    {
        if (!is_array($values) || count($values) > self::LIMIT) {
            throw new InvalidArgumentException('BDC_ERROR_SELECTION');
        }
        $ids = [];
        foreach ($values as $id) {
            if (!is_scalar($id) || !preg_match('/^[1-9][0-9]{0,9}$/D', (string)$id) || (float)$id > 2147483647) {
                throw new InvalidArgumentException('BDC_ERROR_SELECTION');
            }
            $ids[(int)$id] = (int)$id;
        }
        if (!$ids) { throw new InvalidArgumentException('BDC_ERROR_SELECTION'); }
        return array_values($ids);
    }

    public static function filters(array $input): array
    {
        $f = [];
        foreach (['q', 'ip', 'from', 'to'] as $key) {
            $f[$key] = substr(self::text($input[$key] ?? ''), 0, 150);
        }
        foreach (['from', 'to'] as $key) {
            if ($f[$key] !== '') {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $f[$key]);
                if (!$date || $date->format('Y-m-d') !== $f[$key] || $f[$key] < '1900-01-01' || $f[$key] > '9998-12-31') {
                    throw new InvalidArgumentException('BDC_ERROR_DATES');
                }
            }
        }
        if ($f['from'] !== '' && $f['to'] !== '' && $f['from'] > $f['to']) {
            throw new InvalidArgumentException('BDC_ERROR_DATES');
        }
        $f['orders'] = in_array($input['orders'] ?? '', ['none', 'any', 'with'], true) ? $input['orders'] : 'none';
        $f['signal'] = in_array($input['signal'] ?? '', ['never', 'url', 'unapproved'], true) ? $input['signal'] : '';
        $f['sort'] = in_array($input['sort'] ?? '', ['newest', 'oldest', 'name'], true) ? $input['sort'] : 'newest';
        $f['page'] = max(1, min(1000000, (int)self::text($input['page'] ?? 1)));
        return $f;
    }

    private static function like(string $text): string
    {
        return zen_db_input('%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $text) . '%');
    }

    public static function where(array $f): string
    {
        $where = ['c.customers_id > 0'];
        if ($f['q'] !== '') {
            $q = self::like($f['q']);
            $where[] = "(CONCAT(c.customers_firstname, ' ', c.customers_lastname) LIKE '$q' ESCAPE '!' OR c.customers_email_address LIKE '$q' ESCAPE '!')";
        }
        if ($f['ip'] !== '') {
            $ip = self::like($f['ip']);
            $where[] = "c.registration_ip LIKE '$ip' ESCAPE '!'";
        }
        if ($f['from'] !== '') {
            $where[] = "ci.customers_info_date_account_created >= '" . zen_db_input($f['from']) . " 00:00:00'";
        }
        if ($f['to'] !== '') {
            $next = (new DateTimeImmutable($f['to']))->modify('+1 day')->format('Y-m-d');
            $where[] = "ci.customers_info_date_account_created < '$next 00:00:00'";
        }
        if ($f['orders'] !== 'any') {
            $where[] = ($f['orders'] === 'none' ? 'NOT ' : '') . 'EXISTS (SELECT 1 FROM ' . TABLE_ORDERS . ' o WHERE o.customers_id = c.customers_id)';
        }
        if ($f['signal'] === 'never') {
            $where[] = '(ci.customers_info_number_of_logons IS NULL OR ci.customers_info_number_of_logons = 0)';
        } elseif ($f['signal'] === 'url') {
            $where[] = "CONCAT(c.customers_firstname, ' ', c.customers_lastname) REGEXP 'https?://|www[.]'";
        } elseif ($f['signal'] === 'unapproved') {
            $where[] = 'c.customers_authorization <> 0';
        }
        return implode(' AND ', $where);
    }

    public static function columns(): string
    {
        return 'c.customers_id, c.customers_firstname, c.customers_lastname, c.customers_email_address, c.registration_ip, ci.customers_info_date_account_created AS created, ci.customers_info_date_of_last_logon AS last_login, (SELECT COUNT(*) FROM ' . TABLE_ORDERS . ' o WHERE o.customers_id = c.customers_id) AS order_count';
    }

    public static function source(): string
    {
        return TABLE_CUSTOMERS . ' c LEFT JOIN ' . TABLE_CUSTOMERS_INFO . ' ci ON ci.customers_info_id = c.customers_id';
    }

    public static function rows($db, array $ids): array
    {
        $ids = self::ids($ids);
        $result = $db->Execute('SELECT ' . self::columns() . ' FROM ' . self::source() . ' WHERE c.customers_id IN (' . implode(',', $ids) . ') ORDER BY c.customers_id');
        $rows = [];
        foreach ($result as $row) { $rows[(int)$row['customers_id']] = $row; }
        return $rows;
    }

    public static function stage(array $rows, int $admin, bool $allowOrders, bool $deleteReviews): array
    {
        if (!$rows || count($rows) > self::LIMIT) { throw new InvalidArgumentException('BDC_ERROR_SELECTION'); }
        return ['token' => bin2hex(random_bytes(32)), 'expires' => time() + self::TTL, 'admin' => $admin,
            'rows' => $rows, 'allow_orders' => $allowOrders, 'delete_reviews' => $deleteReviews];
    }

    // Consume the pending selection before invoking any destructive code. Refresh cannot replay it.
    public static function consume(array &$session, string $token, int $admin): array
    {
        $pending = $session['bulk_delete_customers_pending'] ?? null;
        if (!is_array($pending) || $token === '' || !hash_equals($pending['token'], $token)
            || $pending['admin'] !== $admin || $pending['expires'] < time()) {
            throw new InvalidArgumentException('BDC_ERROR_EXPIRED');
        }
        unset($session['bulk_delete_customers_pending']);
        return $pending;
    }

    public static function execute($db, $notifier, array $pending): array
    {
        $counts = ['deleted' => 0, 'skipped' => 0];
        foreach ($pending['rows'] as $id => $snapshot) {
            $current = self::rows($db, [(int)$id]);
            $row = $current[$id] ?? null;
            if (!$row || (!$pending['allow_orders'] && (int)$row['order_count'] > 0)
                || $row['customers_firstname'] !== $snapshot['customers_firstname']
                || $row['customers_lastname'] !== $snapshot['customers_lastname']
                || $row['customers_email_address'] !== $snapshot['customers_email_address']) {
                ++$counts['skipped'];
                continue;
            }
            $notifier->notify('NOTIFIER_ADMIN_ZEN_CUSTOMERS_DELETE_CONFIRM', ['customers_id' => (int)$id]);
            $customer = new Customer((int)$id);
            $customer->delete($pending['delete_reviews'], false);
            ++$counts['deleted'];
        }
        return $counts;
    }
}
