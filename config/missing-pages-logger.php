<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Missing Pages Logging
    |--------------------------------------------------------------------------
    |
    | Enable or disable logging of missing pages (404 errors).
    | When enabled, missing pages will be logged to YAML files
    | in the storage directory for later analysis.
    |
    */
    'enabled' => env('PECOTAMIC_MISSING_PAGES_LOGGER_ENABLED', true),
];
