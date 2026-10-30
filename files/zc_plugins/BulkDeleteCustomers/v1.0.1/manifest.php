<?php
if (!defined('IS_ADMIN_FLAG')) { die('Illegal Access'); }
return [
    'pluginVersion' => 'v1.0.1',
    'pluginName' => 'Bulk Delete Customers',
    'pluginDescription' => 'Find and review customer accounts, then remove selected accounts in bulk.',
    'pluginAuthor' => 'Melanie Prough, PRO-Webs, Inc.',
    'pluginId' => 0,
    'zcVersions' => ['v200', 'v210', 'v220'],
    'changelog' => 'https://github.com/mprough/Bulk-Delete-Customers-for-Zen-Cart/blob/main/CHANGELOG.md',
    'github_repo' => 'https://github.com/mprough/Bulk-Delete-Customers-for-Zen-Cart',
    'pluginGroups' => [],
];
