<?php

return [

    'disk' => env('STORAGE_CLEANUP_DISK', 'public'),

    'paths' => explode(',', env('STORAGE_CLEANUP_PATH', 'dpm/exports,dpm/files')),

    'retention_days' => env('STORAGE_CLEANUP_RETENTION_DAYS', 30),

];
