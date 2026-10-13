<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Chamber near-full occupancy threshold (0–1)
    |--------------------------------------------------------------------------
    | Alerts fire when stored quantity / capacity (or occupied slots / total
    | slots when capacity is unset) reaches this ratio.
    */
    'occupancy_near_full_ratio' => (float) env('CSMS_OCCUPANCY_NEAR_FULL_RATIO', 0.90),
];
