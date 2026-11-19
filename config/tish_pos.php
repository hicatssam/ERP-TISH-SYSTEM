<?php

return [
    // Conservative starting policy; adjust thresholds for your restaurant.
    // The request is checked again on the server, never only in JavaScript.
    'manager_discount_percentage' => (float) env('TISH_POS_MANAGER_DISCOUNT_PERCENT', 10),
    'manager_discount_fixed' => (float) env('TISH_POS_MANAGER_DISCOUNT_FIXED', 20),
];
