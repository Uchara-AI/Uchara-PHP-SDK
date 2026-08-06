<?php

namespace Uchara\SDK\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

trait MocksHttp
{
    /**
     * Build a Guzzle client backed by a MockHandler.
     *
     * @param array<int,Response> $responses
     * @param array<int,array<string,mixed>> $history Populated with request/response pairs
     */
    protected function mockClient(array $responses, array &$history = []): Client
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($history));

        return new Client(['handler' => $handlerStack, 'http_errors' => false]);
    }

    protected function jsonResponse(int $status, array $body, array $headers = []): Response
    {
        return new Response($status, array_merge(['Content-Type' => 'application/json'], $headers), json_encode($body));
    }
}
