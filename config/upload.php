<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Max upload size for Excel imports (megabytes)
    |--------------------------------------------------------------------------
    |
    | Must be <= nginx client_max_body_size and PHP post_max_size /
    | upload_max_filesize on the server.
    |
    */
    'max_mb' => (int) env('UPLOAD_MAX_MB', 520),
];
