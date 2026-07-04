<?php

return [
    'redis_channel' => env('SOCKET_REDIS_CHANNEL', 'mobi:import-events'),

    'internal_url' => env('SOCKET_INTERNAL_URL', 'http://127.0.0.1:6001'),

    'internal_secret' => env('SOCKET_INTERNAL_SECRET', 'mobi-socket-internal'),
];
