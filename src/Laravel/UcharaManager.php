<?php

namespace Uchara\SDK\Laravel;

use Uchara\SDK\AgentSDK;
use Uchara\SDK\ServerSDK;
use Uchara\SDK\Uchara;
use Uchara\SDK\VisitorSDK;

/**
 * Resolves and caches Uchara SDK instances from the package configuration.
 *
 * The manager is registered as the `uchara` singleton by the service provider.
 * Calling methods on the manager (or the facade) forwards to the default SDK,
 * so `Uchara::listMembers()` works out of the box.
 */
class UcharaManager
{
    /** @var array<string,mixed> */
    protected array $config;

    protected ?ServerSDK $server = null;

    protected ?AgentSDK $agent = null;

    protected ?VisitorSDK $visitor = null;

    /**
     * @param array<string,mixed> $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get a configuration value.
     *
     * @return mixed
     */
    public function config(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->config;
        }

        return $this->config[$key] ?? $default;
    }

    public function server(): ServerSDK
    {
        if ($this->server === null) {
            $this->server = Uchara::server(
                (string) ($this->config['api_url'] ?? ''),
                isset($this->config['api_key']) ? (string) $this->config['api_key'] : null,
                (int) ($this->config['timeout'] ?? 30),
                $this->config['client'] ?? null
            );
        }

        return $this->server;
    }

    public function agent(): AgentSDK
    {
        if ($this->agent === null) {
            $accessToken = $this->config['access_token'] ?? null;

            $this->agent = Uchara::agent(
                (string) ($this->config['api_url'] ?? ''),
                is_string($accessToken) ? $accessToken : null,
                (int) ($this->config['timeout'] ?? 30),
                $this->config['client'] ?? null
            );
        }

        return $this->agent;
    }

    public function visitor(): VisitorSDK
    {
        if ($this->visitor === null) {
            $widgetToken = $this->config['widget_token'] ?? null;
            if (!is_string($widgetToken) || $widgetToken === '') {
                throw new \InvalidArgumentException('Uchara visitor SDK requires a widget_token.');
            }

            $this->visitor = Uchara::visitor(
                (string) ($this->config['api_url'] ?? ''),
                $widgetToken,
                (int) ($this->config['timeout'] ?? 30),
                $this->config['client'] ?? null
            );
        }

        return $this->visitor;
    }

    /**
     * Return the default SDK based on the `default` config value.
     */
    public function sdk(): ServerSDK|AgentSDK|VisitorSDK
    {
        return match ($this->config['default'] ?? 'server') {
            'agent' => $this->agent(),
            'visitor' => $this->visitor(),
            default => $this->server(),
        };
    }

    /**
     * Forward unknown method calls to the default SDK.
     *
     * @param array<int,mixed> $arguments
     * @return mixed
     */
    public function __call(string $method, array $arguments)
    {
        return $this->sdk()->{$method}(...$arguments);
    }
}
