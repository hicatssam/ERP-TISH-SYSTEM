<?php

return [
    // Enable after provisioning the Python virtual environment and queue worker.
    'enabled' => (bool) env('TISH_CUTOUTS_ENABLED', false),
    'python' => env('TISH_CUTOUTS_PYTHON', base_path('.venv-menu-cutouts/bin/python')),
    'model' => env('TISH_CUTOUTS_MODEL', 'u2net'),
    'timeout' => (int) env('TISH_CUTOUTS_TIMEOUT', 360),
    'queue_connection' => env('TISH_CUTOUTS_QUEUE_CONNECTION', 'database'),
];
