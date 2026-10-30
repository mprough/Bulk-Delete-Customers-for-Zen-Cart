# Change history

## 1.0.1 - 2026-10-30

- Fixed a fatal error when opening Bulk customer removal by explicitly loading Zen Cart's currencies class before creating the currencies object.
- Added a controller bootstrap regression test so a missing core class load is caught before release.

## 1.0.0 - 2026-10-30

- First release with checkbox selection in the standard Customers list, including the unsearched view.
- Added a filtering page for reviewing potential spam registrations by name/email, registration IP, signup dates, order history and account clues.
- Added review and confirmation, order protection, optional review removal, a 50-account batch limit, one-use confirmation and admin activity records.
- Added encapsulated Plugin Manager installation, menu self-repair, profile permission checks, tests and documentation.
