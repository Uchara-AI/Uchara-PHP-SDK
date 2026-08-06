<?php

namespace Uchara\SDK\Tests;

use PHPUnit\Framework\TestCase;
use Uchara\SDK\Laravel\UcharaManager;
use Uchara\SDK\ServerSDK;
use Uchara\SDK\VisitorSDK;

/**
 * Tests the Laravel manager in isolation. The manager only depends on the SDK
 * classes (not on the Laravel container), so it can be exercised without a full
 * Laravel application. The service provider and facade are thin Laravel
 * bindings and are covered by the package's auto-discovery wiring.
 */
class UcharaManagerTest extends TestCase
{
    use MocksHttp;

    public function testServerIsBuiltFromConfig(): void
    {
        $manager = new UcharaManager([
            'api_url' => 'https://api.example.com',
            'api_key' => 'uchara_sk_test',
            'timeout' => 30,
            'default' => 'server',
        ]);

        $this->assertInstanceOf(ServerSDK::class, $manager->server());
        $this->assertSame('server', $manager->config('default'));
        $this->assertSame('https://api.example.com', $manager->config('api_url'));
        $this->assertNull($manager->config('missing'));
        $this->assertSame('fallback', $manager->config('missing', 'fallback'));
    }

    public function testVisitorIsBuiltFromConfig(): void
    {
        $manager = new UcharaManager([
            'api_url' => 'https://api.example.com',
            'widget_token' => 'widget-token',
            'default' => 'visitor',
        ]);

        $this->assertInstanceOf(VisitorSDK::class, $manager->visitor());
        $this->assertInstanceOf(VisitorSDK::class, $manager->sdk());
    }

    public function testDefaultSdkIsServer(): void
    {
        $manager = new UcharaManager([
            'api_url' => 'https://api.example.com',
            'api_key' => 'uchara_sk_test',
        ]);

        $this->assertInstanceOf(ServerSDK::class, $manager->sdk());
    }

    public function testManagerCachesInstances(): void
    {
        $manager = new UcharaManager([
            'api_url' => 'https://api.example.com',
            'widget_token' => 'widget-token',
        ]);
        $this->assertSame($manager->server(), $manager->server());
        $this->assertSame($manager->visitor(), $manager->visitor());
    }

    public function testVisitorThrowsWhenWidgetTokenMissing(): void
    {
        $manager = new UcharaManager(['api_url' => 'https://api.example.com']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Uchara visitor SDK requires a widget_token.');
        $manager->visitor();
    }

    public function testVisitorThrowsWhenWidgetTokenIsEmptyString(): void
    {
        $manager = new UcharaManager([
            'api_url' => 'https://api.example.com',
            'widget_token' => '',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Uchara visitor SDK requires a widget_token.');
        $manager->visitor();
    }

    public function testMagicCallForwardsToDefaultSdk(): void
    {
        $history = [];
        $manager = new UcharaManager([
            'api_url' => 'https://api.example.com',
            'api_key' => 'uchara_sk_test',
            'client' => $this->mockClient([
                $this->jsonResponse(200, ['ok' => true, 'data' => [['id' => 'm1']]]),
            ], $history),
        ]);

        $members = $manager->listMembers();
        $this->assertCount(1, $members);
        $this->assertSame('/v1/workspace/members', $history[0]['request']->getUri()->getPath());
    }
}
