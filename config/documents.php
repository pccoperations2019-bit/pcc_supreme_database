<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Document Upload — Allowed File Types
    |--------------------------------------------------------------------------
    |
    | Centralized allowlist for /documents/create and the xhr file upload
    | pipeline (FileGeneratePresignedUrl + FileUploadController). Keep this
    | as the single source of truth — frontend reads it via Inertia props
    | (DocumentController::create) so both sides stay in sync. Add new
    | extensions here instead of hard-coding accept=".pdf" in Vue.
    |
    */

    'allowed_extensions' => ['pdf', 'xlsx', 'xls', 'csv'],

    'allowed_mimetypes' => [
        // pdf
        'application/pdf',
        // excel — modern & legacy
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
        'application/vnd.ms-excel', // .xls (also some .csv on Windows)
        // csv — browsers vary
        'text/csv',
        'text/plain',
        'application/csv',
    ],

    // Max upload size in megabytes (applies to both presigned URL request
    // validation and the PUT /xhr/files-upload handler).
    'max_size_mb' => 20,

    // Derived helpers — do not set directly.
    'accept_attribute' => '.pdf,.xlsx,.xls,.csv',
    'accept_label' => 'PDF, Excel (.xlsx, .xls) or CSV',
];
