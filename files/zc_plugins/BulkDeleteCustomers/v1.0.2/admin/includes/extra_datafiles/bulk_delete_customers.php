<?php
if (!defined('IS_ADMIN_FLAG')) { die('Illegal Access'); }
if (!defined('FILENAME_BULK_DELETE_CUSTOMERS')) {
    define('FILENAME_BULK_DELETE_CUSTOMERS', 'bulk_delete_customers');
}
// Core's rule for "q" strips ' + & % ( ) before the page sees it, so a search for O'Brien
// or bob+tag@example.com found nothing. The page escapes the value itself.
if (class_exists('AdminRequestSanitizer', false)) {
    AdminRequestSanitizer::getInstance()->addComplexSanitization([
        'q' => ['sanitizerType' => 'WORDS_AND_SYMBOLS_REGEX', 'method' => 'get', 'pages' => ['bulk_delete_customers']],
    ]);
}
