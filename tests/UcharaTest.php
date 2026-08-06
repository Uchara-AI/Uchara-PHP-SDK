<?php

namespace Uchara\SDK\Tests;

use PHPUnit\Framework\TestCase;
use Uchara\SDK\ServerSDK;
use Uchara\SDK\Uchara;
use Uchara\SDK\VisitorSDK;

class UcharaTest extends TestCase
{
    use MocksHttp;

    public function testServerFactory(): void
    {
        $sdk = Uchara::server('https://api.example.com', 'uchara_sk_test', 30, $this->mockClient([]));
        $this->assertInstanceOf(ServerSDK::class, $sdk);
    }

    public function testVisitorFactory(): void
    {
        $sdk = Uchara::visitor('https://api.example.com', 'widget-token', 30, $this->mockClient([]));
        $this->assertInstanceOf(VisitorSDK::class, $sdk);
    }

    public function testMakeDefaultsToServer(): void
    {
        $sdk = Uchara::make([
            'api_url' => 'https://api.example.com',
            'api_key' => 'uchara_sk_test',
            'client' => $this->mockClient([]),
        ]);
        $this->assertInstanceOf(ServerSDK::class, $sdk);
    }

    public function testMakeVisitor(): void
    {
        $sdk = Uchara::make([
            'api_url' => 'https://api.example.com',
            'widget_token' => 'widget-token',
            'default' => 'visitor',
            'client' => $this->mockClient([]),
        ]);
        $this->assertInstanceOf(VisitorSDK::class, $sdk);
    }

    public function testMakeRequiresApiUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Uchara::make([]);
    }

    public function testMakeVisitorRequiresWidgetToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Uchara::make(['api_url' => 'https://api.example.com', 'default' => 'visitor']);
    }
}
