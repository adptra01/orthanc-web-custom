<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Orthanc Connection
    |--------------------------------------------------------------------------
    |
    | Konfigurasi koneksi ke Orthanc PACS server. Semua nilai dapat diatur
    | melalui environment variable. Orthanc hanya boleh diakses dari
    | jaringan internal, Laravel bertindak sebagai gateway aman.
    |
    */

    'base_url' => rtrim((string) env('ORTHANC_BASE_URL', 'http://localhost:8042'), '/'),

    'username' => env('ORTHANC_USERNAME', 'orthanc'),

    'password' => env('ORTHANC_PASSWORD', 'orthanc'),

    'timeout' => (int) env('ORTHANC_TIMEOUT', 10),

    'verify_ssl' => filter_var(env('ORTHANC_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Upload Settings
    |--------------------------------------------------------------------------
    */

    'upload' => [
        // Max size DICOM upload dalam kilobyte (default 100MB)
        'max_size_kb' => (int) env('ORTHANC_UPLOAD_MAX_KB', 102400),
    ],

    /*
    |--------------------------------------------------------------------------
    | Viewer
    |--------------------------------------------------------------------------
    |
    | Path relative terhadap base_url yang dipakai untuk embed Stone Web Viewer
    | atau viewer lain. Disimpan agar mudah diganti tanpa mengubah kode.
    |
    */

    'viewer' => [
        'stone_web_viewer_path' => env('ORTHANC_STONE_VIEWER_PATH', '/stone-webviewer/index.html'),
        'osimis_viewer_path' => env('ORTHANC_OSIMIS_VIEWER_PATH', '/osimis-viewer/app/index.html'),
        'default' => env('ORTHANC_DEFAULT_VIEWER', 'stone'),
    ],

];
