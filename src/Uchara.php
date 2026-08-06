<?php

namespace Uchara\SDK;

use GuzzleHttp\Client;

/**
 * Factory and static entry points for the Uchara SDK.
 *
 * Provides ergonomic constructors for both the Server and Visitor SDKs, plus a
 * `make()` helper that builds the appropriate SDK from a configuration array.
 */
class Uchara
{
    /**
     * Build a ServerSDK.
     *
     * @param Client|null $client Optional Guzzle client (injectable for tests).
     */
    public static function server(string $apiUrl, ?string $apiKey = null, int $timeout = 30, ?Client $client = null): ServerSDK
    {
        return new ServerSDK($apiUrl, $apiKey, $timeout, $client);
    }

    /**
     * Build a VisitorSDK.
     *
     * @param Client|null $client Optional Guzzle client (injectable for tests).
     */
    public static function visitor(string $apiUrl, string $widgetToken, int $timeout = 30, ?Client $client = null): VisitorSDK
    {
        return new VisitorSDK($apiUrl, $widgetToken, $timeout, $client);
    }

    /**
     * Build an SDK from a configuration array.
     *
     * Supported keys:
     *  - api_url / base_url : API base URL (required)
     *  - api_key            : server API key
     *  - widget_token       : widget token (required for the visitor SDK)
     *  - timeout            : request timeout in seconds (default 30)
     *  - default            : 'server' (default) or 'visitor'
     *
     * @param array<string,mixed> $config
     */
    public static function make(array $config): ServerSDK|VisitorSDK
    {
        $apiUrl = $config['api_url'] ?? $config['base_url'] ?? null;
        if (!is_string($apiUrl) || $apiUrl === '') {
            throw new \InvalidArgumentException('Uchara SDK requires an api_url.');
        }

        $timeout = isset($config['timeout']) ? (int) $config['timeout'] : 30;
        $client = $config['client'] ?? null;

        $type = $config['default'] ?? 'server';

        if ($type === 'visitor') {
            $widgetToken = $config['widget_token'] ?? null;
            if (!is_string($widgetToken) || $widgetToken === '') {
                throw new \InvalidArgumentException('Uchara visitor SDK requires a widget_token.');
            }

            return new VisitorSDK($apiUrl, $widgetToken, $timeout, $client);
        }

        $apiKey = $config['api_key'] ?? null;

        return new ServerSDK($apiUrl, is_string($apiKey) ? $apiKey : null, $timeout, $client);
    }
}
