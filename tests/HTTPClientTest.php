<?php

namespace Uchara\SDK\Tests;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Uchara\SDK\HTTPClient;
use Uchara\SDK\UcharaException;

class HTTPClientTest extends TestCase
{
    use MocksHttp;

    public function testGetSendsQueryParameters(): void
    {
        $history = [];
        $client = $this->mockClient([
            $this->jsonResponse(200, ['ok' => true, 'data' => ['items' => []]]),
        ], $history);

        $http = new HTTPClient('https://api.example.com', 30, $client);
        $http->get('/v1/workspace/members', ['role' => 'admin', 'limit' => 10]);

        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/v1/workspace/members', $request->getUri()->getPath());
        $this->assertSame('role=admin&limit=10', $request->getUri()->getQuery());
    }

    public function testEnvelopeIsUnwrapped(): void
    {
        $client = $this->mockClient([
            $this->jsonResponse(200, ['ok' => true, 'data' => ['id' => 'm1'], 'meta' => ['total' => 1]]),
        ]);

        $http = new HTTPClient('https://api.example.com', 30, $client);
        $this->assertSame(['id' => 'm1'], $http->get('/v1/workspace/members'));
    }

    public function testRequestExposesStatusAndMeta(): void
    {
        $client = $this->mockClient([
            $this->jsonResponse(200, ['ok' => true, 'data' => ['id' => 'm1'], 'meta' => ['total' => 42]]),
        ]);

        $http = new HTTPClient('https://api.example.com', 30, $client);
        $response = $http->request('GET', '/v1/workspace/members');

        $this->assertSame(200, $response->status());
        $this->assertSame(['id' => 'm1'], $response->data());
        $this->assertSame(['total' => 42], $response->meta());
        $this->assertTrue($response->successful());
    }

    public function testNon2xxThrowsStructuredException(): void
    {
        $client = $this->mockClient([
            $this->jsonResponse(404, ['error' => ['message' => 'Member not found', 'code' => 'not_found']]),
        ]);

        $http = new HTTPClient('https://api.example.com', 30, $client);

        try {
            $http->get('/v1/workspace/members/missing');
            $this->fail('Expected UcharaException');
        } catch (UcharaException $e) {
            $this->assertSame(404, $e->getStatus());
            $this->assertSame('Member not found', $e->getMessage());
            $this->assertSame('not_found', $e->getDetails()['code']);
            $this->assertNotNull($e->getResponse());
        }
    }

    public function testOkFalseEnvelopeThrows(): void
    {
        $client = $this->mockClient([
            $this->jsonResponse(200, ['ok' => false, 'error' => ['message' => 'Something failed']]),
        ]);

        $http = new HTTPClient('https://api.example.com', 30, $client);

        $this->expectException(UcharaException::class);
        $this->expectExceptionMessage('Something failed');
        $http->get('/v1/workspace/members');
    }

    public function testAuthTokenIsSentAsBearer(): void
    {
        $history = [];
        $client = $this->mockClient([
            $this->jsonResponse(200, ['ok' => true, 'data' => []]),
        ], $history);

        $http = new HTTPClient('https://api.example.com', 30, $client);
        $http->setAuthToken('uchara_sk_test');
        $http->get('/v1/me');

        $this->assertSame('Bearer uchara_sk_test', $history[0]['request']->getHeaderLine('Authorization'));
    }

    public function testPerRequestHeadersAreSent(): void
    {
        $history = [];
        $client = $this->mockClient([
            $this->jsonResponse(201, ['ok' => true, 'data' => ['id' => 'm1']]),
        ], $history);

        $http = new HTTPClient('https://api.example.com', 30, $client);
        $http->post('/v1/workspace/members', ['email' => 'a@b.com'], ['Idempotency-Key' => 'abc-123']);

        $this->assertSame('abc-123', $history[0]['request']->getHeaderLine('Idempotency-Key'));
    }

    public function test204ReturnsEmptyData(): void
    {
        $client = $this->mockClient([new Response(204)]);

        $http = new HTTPClient('https://api.example.com', 30, $client);
        $this->assertSame([], $http->delete('/v1/workspace/members/m1'));
    }

    public function testRawReturnsBodyString(): void
    {
        $client = $this->mockClient([
            new Response(200, ['Content-Type' => 'text/plain'], "Percakapan #abc\n"),
        ]);

        $http = new HTTPClient('https://api.example.com', 30, $client);
        $this->assertSame("Percakapan #abc\n", $http->raw('GET', '/v1/widget/conversations/c1/download'));
    }
}
