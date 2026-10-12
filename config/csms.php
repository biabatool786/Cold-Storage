<?php

return [
    'accounting_export' => [
        // Set true only when an external accounting integration is configured.
        'enabled' => (bool) env('CSMS_ACCOUNTING_EXPORT_ENABLED', false),
    ],
];
