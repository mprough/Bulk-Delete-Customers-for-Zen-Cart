# Change history

## 1.0.2 - 2026-11-01

- Extended compatibility to Zen Cart 1.5.8 through 3.0.0 and PHP 7.4 through 8.5.
- Fixed Name or email search, which found nothing when the search contained an apostrophe, plus sign, ampersand, percent sign or parenthesis.
- Fixed page counts, which always reported one page, and the Next link, which appeared on the last page.
- Fixed the Next and Previous links, which lost the filters and stayed on the first page.
- Fixed a fatal error on Customers and on Bulk customer removal in a non-English admin on Zen Cart 1.5.8 and 2.0.x under PHP 8. English text now fills any missing translation.
- Fixed select-page on a Customers list showing more than 50 accounts, where it could not clear its own selection.
- Changed the table's IP column heading to Registration IP.
- Removed a duplicate page title and stylesheet from Bulk customer removal.
- Blocked direct requests to the admin page file.

## 1.0.1 - 2026-10-30

- Fixed a fatal error when opening Bulk customer removal by explicitly loading Zen Cart's currencies class before creating the currencies object.
- Added a controller bootstrap regression test so a missing core class load is caught before release.

## 1.0.0 - 2026-10-30

- First release with checkbox selection in the standard Customers list, including the unsearched view.
- Added a filtering page for reviewing potential spam registrations by name/email, registration IP, signup dates, order history and account clues.
- Added review and confirmation, order protection, optional review removal, a 50-account batch limit, one-use confirmation and admin activity records.
- Added encapsulated Plugin Manager installation, menu self-repair, profile permission checks, tests and documentation.
