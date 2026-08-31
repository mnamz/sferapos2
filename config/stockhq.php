<?php

// Integration with the central Stock HQ dashboard (hq.myrccornertrading.com).
// This branch pushes its catalogue + sales to HQ. All pushing is queued, so a
// HQ outage never blocks or slows a sale at the till.

return [
    'enabled' => env('STOCK_HQ_ENABLED', false),
    'url' => env('STOCK_HQ_URL'),            // e.g. https://hq.myrccornertrading.com
    'token' => env('STOCK_HQ_TOKEN'),        // this branch's HQ bearer token
    'branch_id' => env('STOCK_HQ_BRANCH_ID'),
    'queue' => env('STOCK_HQ_QUEUE', 'stockhq'),
    'timeout' => (int) env('STOCK_HQ_TIMEOUT', 15),
];
