<?php

return [
    /*
    |--------------------------------------------------------------------------
    | E-IMZO Server URL
    |--------------------------------------------------------------------------
    |
    | The URL of the E-IMZO server that handles authentication and signature
    | verification. Default is localhost for development.
    |
    */
    'server_url' => env('EIMZO_SERVER_URL', 'http://127.0.0.1:8080'),

    /*
    |--------------------------------------------------------------------------
    | E-IMZO WebSocket URL
    |--------------------------------------------------------------------------
    |
    | The WebSocket URL for E-IMZO client connection.
    | HTTPS sites use wss://127.0.0.1:64443
    | HTTP sites use ws://127.0.0.1:64646
    |
    */
    'ws_url_https' => env('EIMZO_WS_URL_HTTPS', 'wss://127.0.0.1:64443'),
    'ws_url_http' => env('EIMZO_WS_URL_HTTP', 'ws://127.0.0.1:64646'),

    /*
    |--------------------------------------------------------------------------
    | API Keys
    |--------------------------------------------------------------------------
    |
    | API keys for E-IMZO domains. Add your domain and API key here.
    | Format: ['domain' => 'api_key']
    |
    */
    'api_keys' => [
        'localhost' => '96D0C1491615C82B9A54D9989779DF825B690748224C2B04F500F370D51827CE2644D8D4A82C18184D73AB8530BB8ED537269603F61DB0D03D2104ABF789970B',
        '127.0.0.1' => 'A7BCFA5D490B351BE0754130DF03A068F855DB4333D43921125B9CF2670EF6A40370C646B90401955E1F7BC9CDBF59CE0B2C5467D820BE189C845D0B79CFC96F',
    ],
];
