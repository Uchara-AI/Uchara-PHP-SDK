<?php

namespace Uchara\SDK\Tests;

use PHPUnit\Framework\TestCase;
use Uchara\SDK\AgentSDK;
use Uchara\SDK\UcharaException;

class AgentSDKTest extends TestCase
{
    use MocksHttp;

    private function sdk(array $responses, array &$history = []): AgentSDK
    {
        return new AgentSDK(
            'https://api.example.com',
            null,
            30,
            $this->mockClient($responses, $history)
        );
    }

    public function testLoginStoresTokensAndMember(): void
    {
        $history = [];
        $sdk = $this->sdk([
            $this->jsonResponse(200, [
                'ok' => true,
                'data' => [
                    'token' => 'access-token',
                    'refresh_token' => 'refresh-token',
                    'member' => ['id' => 'member-1', 'name' => 'Agent One'],
                    'workspace' => ['id' => 'workspace-1'],
                ],
            ]),
        ], $history);

        $response = $sdk->login('agent@example.com', 'secret', 'acme');

        $this->assertSame('access-token', $sdk->getAccessToken());
        $this->assertSame('refresh-token', $sdk->getRefreshToken());
        $this->assertSame('member-1', $sdk->getMember()['id']);
        $this->assertSame('access-token', $response['token']);

        $request = $history[0]['request'];
        $this->assertSame('/v1/auth/login', $request->getUri()->getPath());
        $this->assertSame([
            'email' => 'agent@example.com',
            'password' => 'secret',
            'slug' => 'acme',
        ], json_decode((string) $request->getBody(), true));
    }

    public function testLoginSetsAuthorizationHeaderForSubsequentRequests(): void
    {
        $history = [];
        $sdk = $this->sdk([
            $this->jsonResponse(200, [
                'ok' => true,
                'data' => ['token' => 'access-token', 'refresh_token' => 'refresh-token'],
            ]),
            $this->jsonResponse(200, [
                'ok' => true,
                'data' => ['id' => 'conversation-1'],
            ]),
        ], $history);

        $sdk->login('agent@example.com', 'secret');
        $sdk->getConversation('conversation-1');

        $this->assertSame('Bearer access-token', $history[1]['request']->getHeaderLine('Authorization'));
    }

    public function testLoginWithTokenStoresBackendCreatedSessionWithoutRequest(): void
    {
        $sdk = $this->sdk([]);

        $sdk->loginWithToken([
            'token' => 'backend-access-token',
            'refresh_token' => 'backend-refresh-token',
            'member' => ['id' => 'member-1'],
        ]);

        $this->assertSame('backend-access-token', $sdk->getAccessToken());
        $this->assertSame('backend-refresh-token', $sdk->getRefreshToken());
        $this->assertSame('member-1', $sdk->getMember()['id']);
    }

    public function testRefreshUsesStoredRefreshTokenAndUpdatesAccessToken(): void
    {
        $history = [];
        $sdk = $this->sdk([
            $this->jsonResponse(200, [
                'ok' => true,
                'data' => [
                    'token' => 'new-access-token',
                    'refresh_token' => 'new-refresh-token',
                    'member' => ['id' => 'member-1'],
                ],
            ]),
        ], $history);
        $sdk->setRefreshToken('old-refresh-token');

        $sdk->refresh();

        $this->assertSame('new-access-token', $sdk->getAccessToken());
        $this->assertSame('new-refresh-token', $sdk->getRefreshToken());
        $this->assertSame(
            ['refresh_token' => 'old-refresh-token'],
            json_decode((string) $history[0]['request']->getBody(), true)
        );
    }

    public function testAutomaticallyRefreshesOnceAndRetriesAfterUnauthorized(): void
    {
        $history = [];
        $sdk = $this->sdk([
            $this->jsonResponse(401, ['ok' => false, 'error' => ['message' => 'expired']]),
            $this->jsonResponse(200, [
                'ok' => true,
                'data' => ['token' => 'fresh-access-token', 'refresh_token' => 'fresh-refresh-token'],
            ]),
            $this->jsonResponse(200, ['ok' => true, 'data' => []]),
        ], $history);
        $sdk->setAccessToken('expired-access-token');
        $sdk->setRefreshToken('valid-refresh-token');

        $this->assertSame([], $sdk->listConversations());
        $this->assertCount(3, $history);
        $this->assertSame('/v1/auth/refresh', $history[1]['request']->getUri()->getPath());
        $this->assertSame('Bearer fresh-access-token', $history[2]['request']->getHeaderLine('Authorization'));
    }

    public function testRefreshWithoutTokenThrows(): void
    {
        $sdk = $this->sdk([]);

        $this->expectException(UcharaException::class);
        $sdk->refresh();
    }

    public function testSendMessageUsesAuthenticatedAgentAndIgnoresIdentityOverrides(): void
    {
        $history = [];
        $sdk = $this->sdk([
            $this->jsonResponse(201, [
                'ok' => true,
                'data' => ['id' => 'message-1', 'sender_type' => 'agent'],
            ]),
        ], $history);
        $sdk->setAccessToken('access-token');

        $sdk->sendMessage('conversation-1', [
            'content' => 'Hello from the agent',
            'sender_type' => 'bot',
            'sender_id' => 'someone-else',
        ]);

        $request = $history[0]['request'];
        $this->assertSame('/v1/conversations/conversation-1/messages', $request->getUri()->getPath());
        $this->assertSame('Bearer access-token', $request->getHeaderLine('Authorization'));
        $this->assertSame(
            ['content' => 'Hello from the agent'],
            json_decode((string) $request->getBody(), true)
        );
    }
}
