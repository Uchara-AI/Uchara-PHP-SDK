<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default SDK
    |--------------------------------------------------------------------------
    |
    | The default SDK returned by the Uchara facade / manager when no explicit
    | SDK is requested. Supported values: 'server', 'agent' or 'visitor'.
    |
    */

    'default' => env('UCHARA_DEFAULT', 'server'),

    /*
    |--------------------------------------------------------------------------
    | API URL
    |--------------------------------------------------------------------------
    |
    | Base URL of the Uchara API. Override with the UCHARA_API_URL env var.
    |
    */

    'api_url' => env('UCHARA_API_URL', 'https://api.uchara.com'),

    /*
    |--------------------------------------------------------------------------
    | Server API Key
    |--------------------------------------------------------------------------
    |
    | Server SDK API key (uchara_sk_...). Used for server-to-server calls.
    |
    */

    'api_key' => env('UCHARA_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Agent Access Token
    |--------------------------------------------------------------------------
    |
    | JWT access token for the Agent SDK. Agents can also authenticate at
    | runtime with AgentSDK::login().
    |
    */

    'access_token' => env('UCHARA_ACCESS_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Widget Token
    |--------------------------------------------------------------------------
    |
    | Widget token for the Visitor SDK. Used when 'default' is 'visitor'.
    |
    */

    'widget_token' => env('UCHARA_WIDGET_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | Request timeout in seconds.
    |
    */

    'timeout' => (int) env('UCHARA_TIMEOUT', 30),

];
