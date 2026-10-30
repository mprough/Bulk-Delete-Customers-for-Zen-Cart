# Bulk Delete Customers for Zen Cart

Version 1.0.1, by Melanie Prough, PRO-Webs, Inc. | [PRO-Webs.net](https://pro-webs.net/)

Remove customer accounts in batches using checkbox selection. Checkboxes appear in the regular Customers list, including its unsearched view. A separate **Customers > Bulk customer removal** page adds filters to help identify possible spam registrations.

## Features

- Individual checkboxes and select-page controls in the standard Customers list, with or without a search.
- Review the selected accounts before confirming permanent removal.
- Search by name or email text, registration IP text, inclusive signup date range, and order history.
- Filter for accounts that never logged in, website URLs in customer names, or accounts restricted or awaiting approval.
- Sort by newest signup, oldest signup, or customer name.
- See customer ID, name, email, registration IP, signup date, last login, and order count on the filtering and confirmation pages.
- Process up to 50 accounts per batch. Selections are limited to the current page and are cleared when navigating or filtering.
- Protect accounts with orders by default. The filtering page offers an explicit override to remove their accounts while retaining orders.
- Keep customer reviews by default, or explicitly delete them using the filtering page.
- Use Zen Cart's normal Customer deletion routine and pre-delete notification, allowing existing deletion observers to run.
- Admin activity records, CSRF validation, and a one-use confirmation tied to the reviewing administrator with a 15-minute expiry.
- No core or template overwrites, new tables, cron jobs, storefront changes, or external services.

## Compatibility

Designed for Zen Cart 2.0.x, 2.1.x, and 2.2.x. Use PHP only within your installed Zen Cart release's supported range: the plugin's PHP syntax supports 8.0 through 8.5. CI checks this range; see the Actions results for the tested source revision. This does not extend Zen Cart's own PHP compatibility.

## Installation

1. Back up your database and files. Try the plugin on a development copy first.
2. Upload the contents of `files/` to your store root. The resulting path is `zc_plugins/BulkDeleteCustomers/v1.0.1/`. There is no admin folder to rename or core file to replace.
3. Open **Modules > Plugin Manager**, select **Bulk Delete Customers**, and install/enable version 1.0.1.
4. Open **Customers > Customers** for the standard list checkboxes, or **Customers > Bulk customer removal** for additional filters.
5. For non-superuser administrators, grant both the Customers page and Bulk customer removal page in **Admin > Admin profiles**. The plugin requires both permissions. Superusers have access automatically.

The only database addition is the hidden `PLUGIN_BULK_DELETE_CUSTOMERS_VERSION` configuration marker and the plugin's admin menu registration. There are no editable configuration settings. A runtime bootstrap repairs a missing menu entry without rewriting an existing registration or its assigned permissions.

## Regular Customers list

The added selection column appears on every listing page, including the initial unsearched list. Tick individual accounts or **Select this page**, then **Review selected**. If your standard listing displays more than 50 rows, select-page chooses only its first 50 accounts. Without JavaScript, individual checkboxes and review submission still work; the server enforces the batch limit.

The normal listing always protects accounts with orders and retains reviews. Protected accounts are excluded from the review and reported, provided at least one eligible account remains. If none remain, the page reports that there are no eligible accounts. Use the filtering page if you deliberately need to include accounts with orders or delete reviews.

## Find possible spam accounts

The filtering page initially shows customers with no orders. Use one or more filters:

| Filter | Behavior |
| --- | --- |
| Name or email contains | Literal partial match in the full name or email. Useful for a repeated spam name or email domain. Percent and underscore are treated as text. |
| Registration IP contains | Literal partial match, including a whole IPv4/IPv6 address or an address prefix. Blank addresses cannot establish shared origin. |
| Signup date range | Both dates inclusive. Either date may be omitted. Uses the database's stored signup dates. |
| Order history | No orders, all customers, or customers with orders. |
| Never logged in | Stored login count is zero or missing. |
| Website URL in name | Name contains `http://`, `https://`, or `www.` under the database's regular expression rules. |
| Restricted or awaiting approval | Zen Cart customer authorization value is not zero. This includes banned accounts. |

Account filters are clues for review, not a spam score. Legitimate accounts can share IP addresses, never log in, or require approval. The plugin never automatically selects or removes accounts based on these filters. Text filters and counts may take longer on very large databases.

Choose the accounts, optionally allow accounts with orders and/or delete reviews, then select **Review selected**. The confirmation lists the exact eligible selection and the chosen review behavior. Check the acknowledgement and select **Permanently remove selected customers**, or cancel.

## What removal does

Zen Cart removes the customer account, addresses, saved cart and associated core records through `Customer::delete()`. The exact cleanup follows the installed Zen Cart version and its observers. Reviews are retained using normal Zen Cart behavior unless you explicitly choose to delete them.

Orders, invoices, and their existing customer/contact details are retained. This is account removal, not anonymization or an erasure tool for order history. Other plugins may own additional customer data; their existing deletion observers determine their cleanup. Unrelated plugin tables are not guessed or deleted by this plugin.

Immediately before each removal, the plugin checks whether the account still exists, whether its name or email changed, and whether an account protected by the order rule now has orders. Such accounts are skipped. It does not lock checkout or other admin work; avoid running customer cleanup concurrently with account/order imports or other customer maintenance.

The confirmation is consumed before deletion starts. Refreshing or resubmitting cannot repeat a batch. Opening another review replaces the prior pending selection in that admin session. If an error interrupts a batch, some accounts may already be removed. Inspect the remaining list and Zen Cart error logs before reviewing another selection. There is no built-in undo; restore from your database backup if necessary.

## Upgrade and removal

Upgrade from 1.0.0 by uploading the new version directory and selecting version 1.0.1 in Plugin Manager. No legacy loose-file installation needs migration. Disable the plugin to stop its controls from loading. Uninstall through Plugin Manager to remove its version marker, menu registration, and plugin page permission mappings. Uninstall never removes customer accounts or orders. Remove the plugin's version files only after Plugin Manager has finished.

If the menu does not appear, confirm the plugin is installed/enabled, reload admin, and check the profile permissions. If checkboxes are absent, confirm both permissions and that a customized `customers.php` still fires `NOTIFY_ADMIN_CUSTOMERS_LISTING_HEADER` and `NOTIFY_ADMIN_CUSTOMERS_LISTING_ELEMENT`. This plugin does not replace custom listing code.

## Validation

`php tests/bootstrap.php` opens the real admin page entry point with the currencies class initially unloaded. `php tests/runtime.php` checks selection validation, date and text filters, confirmation ownership/expiry/replay, changed and missing accounts, order protection, review options, native listing hooks, and permission visibility using test doubles. `php tests/lifecycle.php` exercises install, idempotent upgrade, runtime menu repair, and uninstall against a simulated Zen Cart environment. `python3 tests/package.py` checks the package and creates a ZIP when an output path is provided. The PHP CI matrix runs from 8.0 through 8.5. A separate test exercises Zen Cart 2.2.2's actual installer and Customer class against a disposable MariaDB database.

These tests are not a substitute for installation and deletion testing on a backed-up development store with your exact extensions. No live customer data is used by the tests.

## Support and license

[Source repository](https://github.com/mprough/Bulk-Delete-Customers-for-Zen-Cart) | [Report a bug or request assistance](https://prowebsinc.zohodesk.com/portal/en/newticket)

Distributed under GPL version 2. Free distribution is provided without warranty. **Installation Extra**. Installation, configuration, and customization are not included. Include your Zen Cart version, PHP version, plugin version, reproduction steps, and relevant sanitized error logs in support requests. Do not post customer data or credentials publicly. No Zen Cart plugin directory ID has been assigned yet.

Credits: Zen Cart Development Team for the core customer deletion API and admin listing notification hooks. Plugin development by Melanie Prough, PRO-Webs, Inc.
