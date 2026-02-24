<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk used for file uploads.
    | Supported: "s3", "local", "public"
    |
    */
    'disk' => env('ONBOARDING_UPLOAD_DISK', 's3'),

    /*
    |--------------------------------------------------------------------------
    | Allowed File Types
    |--------------------------------------------------------------------------
    |
    | MIME types allowed for file question uploads.
    | Add or remove types as needed.
    |
    */
    'allowed_mimes' => [
        'pdf',
        'jpg',
        'jpeg',
        'png',
        'docx',
        'doc',
        'xlsx',
        'xls',
        'csv',
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum File Size (KB)
    |--------------------------------------------------------------------------
    |
    | Maximum allowed file size in kilobytes.
    | Default: 5120 KB (5 MB)
    |
    */
    'max_file_size_kb' => env('ONBOARDING_MAX_FILE_SIZE_KB', 5120),

    /*
    |--------------------------------------------------------------------------
    | Maximum Files Per Question
    |--------------------------------------------------------------------------
    |
    | Maximum number of files that can be uploaded per file-type question.
    |
    */
    'max_files_per_question' => env('ONBOARDING_MAX_FILES', 10),

    /*
    |--------------------------------------------------------------------------
    | Signed URL Expiry (Minutes)
    |--------------------------------------------------------------------------
    |
    | How long temporary S3 signed URLs remain valid.
    |
    */
    'url_expiry_minutes' => env('ONBOARDING_URL_EXPIRY_MINUTES', 60),

];
