<?php

namespace Uchara\SDK\Tests;

use PHPUnit\Framework\TestCase;
use Uchara\SDK\ServerSDK;
use Uchara\SDK\UcharaException;

class ServerSDKTest extends TestCase
{
    use MocksHttp;

    private function sdk(array $responses, array &$history = []): ServerSDK
    {
        return new ServerSDK('https://api.example.com', 'uchara_sk_test', 30, $this->mockClient($responses, $history));
    }

    public function testCreateAgentSessionUsesServerApiKey(): void
    {
        $history = [];
        $sdk = $this->sdk([
            $this->jsonResponse(200, [
                'ok' => true,
                'data' => [
                    'token' => 'agent-access-token',
                    'refresh_token' => 'agent-refresh-token',
                    'member' => ['id' => 'member-1'],
                ],
            ]),
        ], $history);

        $session = $sdk->createAgentSession('agent@example.com');

        $this->assertSame('agent-access-token', $session['token']);
        $this->assertSame('/v1/auth/agent-token', $history[0]['request']->getUri()->getPath());
        $this->assertSame('Bearer uchara_sk_test', $history[0]['request']->getHeaderLine('Authorization'));
        $this->assertSame(['email' => 'agent@example.com'], json_decode((string) $history[0]['request']->getBody(), true));
    }

    public function testListMembersSendsQuery(): void
    {
        $history = [];
        $sdk = $this->sdk([
            $this->jsonResponse(200, ['ok' => true, 'data' => [['id' => 'm1', 'name' => 'Alice']]]),
        ], $history);

        $members = $sdk->listMembers(['role' => 'admin']);

        $this->assertCount(1, $members);
        $this->assertSame('Alice', $members[0]['name']);
        $this->assertSame('/v1/workspace/members', $history[0]['request']->getUri()->getPath());
        $this->assertSame('role=admin', $history[0]['request']->getUri()->getQuery());
    }

    public function testGetMember(): void
    {
        $history = [];
        $sdk = $this->sdk([
            $this->jsonResponse(200, ['ok' => true, 'data' => ['id' => 'm1']]),
        ], $history);

        $sdk->getMember('m1');
        $this->assertSame('/v1/workspace/members/m1', $history[0]['request']->getUri()->getPath());
    }

    public function testCreateMemberSendsIdempotencyKey(): void
    {
        $history = [];
        $sdk = $this->sdk([
            $this->jsonResponse(201, ['ok' => true, 'data' => ['id' => 'm1']]),
        ], $history);

        $sdk->createMember(['email' => 'a@b.com', 'role' => 'agent'], 'idem-1');

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/v1/workspace/members', $request->getUri()->getPath());
        $this->assertSame('idem-1', $request->getHeaderLine('Idempotency-Key'));
        $this->assertSame('a@b.com', json_decode((string) $request->getBody(), true)['email']);
    }

    public function testCreateMemberWithoutIdempotencyKeyHasNoHeader(): void
    {
        $history = [];
        $sdk = $this->sdk([
            $this->jsonResponse(201, ['ok' => true, 'data' => ['id' => 'm1']]),
        ], $history);

        $sdk->createMember(['email' => 'a@b.com']);
        $this->assertSame('', $history[0]['request']->getHeaderLine('Idempotency-Key'));
    }

    public function testUpdateMember(): void
    {
        $history = [];
        $sdk = $this->sdk([
            $this->jsonResponse(200, ['ok' => true, 'data' => ['updated' => true]]),
        ], $history);

        $sdk->updateMember('m1', ['name' => 'Bob']);
        $request = $history[0]['request'];
        $this->assertSame('PATCH', $request->getMethod());
        $this->assertSame('/v1/workspace/members/m1', $request->getUri()->getPath());
    }

    public function testUpdateMemberRoleUsesCompatibilityRoute(): void
    {
        $history = [];
        $sdk = $this->sdk([
            $this->jsonResponse(200, ['ok' => true, 'data' => ['updated' => true]]),
        ], $history);

        $sdk->updateMemberRole('m1', 'admin');
        $request = $history[0]['request'];
        $this->assertSame('PATCH', $request->getMethod());
        $this->assertSame('/v1/workspace/members/m1/role', $request->getUri()->getPath());
        $this->assertSame('admin', json_decode((string) $request->getBody(), true)['role']);
    }

    public function testDeactivateAndReactivate(): void
    {
        $history = [];
        $sdk = $this->sdk([
            $this->jsonResponse(200, ['ok' => true, 'data' => ['updated' => true]]),
            $this->jsonResponse(200, ['ok' => true, 'data' => ['updated' => true]]),
        ], $history);

        $sdk->deactivateMember('m1');
        $sdk->reactivateMember('m1');

        $this->assertSame('/v1/workspace/members/m1/deactivate', $history[0]['request']->getUri()->getPath());
        $this->assertSame('/v1/workspace/members/m1/reactivate', $history[1]['request']->getUri()->getPath());
    }

    public function testDeleteMember(): void
    {
        $history = [];
        $sdk = $this->sdk([
            $this->jsonResponse(200, ['ok' => true, 'data' => ['deleted' => true]]),
        ], $history);

        $sdk->deleteMember('m1');
        $this->assertSame('DELETE', $history[0]['request']->getMethod());
        $this->assertSame('/v1/workspace/members/m1', $history[0]['request']->getUri()->getPath());
    }

    public function testInviteListAndRevoke(): void
    {
        $history = [];
        $sdk = $this->sdk([
            $this->jsonResponse(201, ['ok' => true, 'data' => ['id' => 'i1']]),
            $this->jsonResponse(200, ['ok' => true, 'data' => [['id' => 'i1']]]),
            $this->jsonResponse(200, ['ok' => true, 'data' => ['deleted' => true]]),
        ], $history);

        $sdk->inviteMember(['email' => 'x@y.com', 'role' => 'agent']);
        $sdk->listInvites();
        $sdk->revokeInvite('i1');

        $this->assertSame('/v1/workspace/invites', $history[0]['request']->getUri()->getPath());
        $this->assertSame('/v1/workspace/invites', $history[1]['request']->getUri()->getPath());
        $this->assertSame('/v1/workspace/invites/i1', $history[2]['request']->getUri()->getPath());
    }

    public function testAgentAliasesMapToMemberMethods(): void
    {
        $history = [];
        $sdk = $this->sdk([
            $this->jsonResponse(200, ['ok' => true, 'data' => []]), // listAgents
            $this->jsonResponse(200, ['ok' => true, 'data' => ['id' => 'a1']]), // getAgent
            $this->jsonResponse(201, ['ok' => true, 'data' => ['id' => 'a1']]), // createAgent
            $this->jsonResponse(200, ['ok' => true, 'data' => ['updated' => true]]), // updateAgent
            $this->jsonResponse(200, ['ok' => true, 'data' => ['updated' => true]]), // updateAgentRole
            $this->jsonResponse(200, ['ok' => true, 'data' => ['updated' => true]]), // deactivateAgent
            $this->jsonResponse(200, ['ok' => true, 'data' => ['updated' => true]]), // reactivateAgent
            $this->jsonResponse(200, ['ok' => true, 'data' => ['deleted' => true]]), // deleteAgent
            $this->jsonResponse(201, ['ok' => true, 'data' => ['id' => 'i1']]), // inviteAgent
        ], $history);

        $sdk->listAgents();
        $sdk->getAgent('a1');
        $sdk->createAgent(['email' => 'a@b.com']);
        $sdk->updateAgent('a1', ['name' => 'X']);
        $sdk->updateAgentRole('a1', 'admin');
        $sdk->deactivateAgent('a1');
        $sdk->reactivateAgent('a1');
        $sdk->deleteAgent('a1');
        $sdk->inviteAgent(['email' => 'a@b.com']);

        $paths = array_map(fn ($h) => $h['request']->getUri()->getPath(), $history);
        $this->assertSame([
            '/v1/workspace/members',
            '/v1/workspace/members/a1',
            '/v1/workspace/members',
            '/v1/workspace/members/a1',
            '/v1/workspace/members/a1/role',
            '/v1/workspace/members/a1/deactivate',
            '/v1/workspace/members/a1/reactivate',
            '/v1/workspace/members/a1',
            '/v1/workspace/invites',
        ], $paths);
    }

    public function testChannelCrud(): void
    {
        $history = [];
        $sdk = $this->sdk([
            $this->jsonResponse(200, ['ok' => true, 'data' => [['id' => 'c1', 'name' => 'Web']]]), // list
            $this->jsonResponse(201, ['ok' => true, 'data' => ['id' => 'c2']]), // create
            $this->jsonResponse(200, ['ok' => true, 'data' => ['updated' => true]]), // update
            $this->jsonResponse(200, ['ok' => true, 'data' => ['deleted' => true]]), // delete
        ], $history);

        $sdk->listChannels();
        $sdk->createChannel(['name' => 'WhatsApp', 'type' => 'whatsapp']);
        $sdk->updateChannel('c2', ['name' => 'WA']);
        $sdk->deleteChannel('c2');

        $this->assertSame('/v1/channels', $history[0]['request']->getUri()->getPath());
        $this->assertSame('/v1/channels', $history[1]['request']->getUri()->getPath());
        $this->assertSame('/v1/channels/c2', $history[2]['request']->getUri()->getPath());
        $this->assertSame('/v1/channels/c2', $history[3]['request']->getUri()->getPath());
    }

    public function testGetChannelFiltersFromList(): void
    {
        $sdk = $this->sdk([
            $this->jsonResponse(200, ['ok' => true, 'data' => [
                ['id' => 'c1', 'name' => 'Web'],
                ['id' => 'c2', 'name' => 'WA'],
            ]]),
        ]);

        $channel = $sdk->getChannel('c2');
        $this->assertSame('WA', $channel['name']);
    }

    public function testGetChannelNotFoundThrows(): void
    {
        $sdk = $this->sdk([
            $this->jsonResponse(200, ['ok' => true, 'data' => [['id' => 'c1']]]),
        ]);

        $this->expectException(UcharaException::class);
        $sdk->getChannel('missing');
    }

    public function testMessageAliases(): void
    {
        $history = [];
        $sdk = $this->sdk([
            $this->jsonResponse(201, ['ok' => true, 'data' => ['id' => 'msg1']]),
            $this->jsonResponse(200, ['ok' => true, 'data' => []]),
            $this->jsonResponse(200, ['ok' => true, 'data' => []]),
        ], $history);

        $sdk->sendMessageToConversation('conv1', ['content' => 'hi']);
        $sdk->listMessages('conv1');
        $sdk->getConversationMessages('conv1');

        $this->assertSame('/v1/conversations/conv1/messages', $history[0]['request']->getUri()->getPath());
        $this->assertSame('/v1/conversations/conv1/messages', $history[1]['request']->getUri()->getPath());
        $this->assertSame('/v1/conversations/conv1/messages', $history[2]['request']->getUri()->getPath());
    }

    public function testSendMessageDefaultsSenderTypeToBot(): void
    {
        $history = [];
        $sdk = $this->sdk([
            $this->jsonResponse(201, ['ok' => true, 'data' => ['id' => 'msg1']]),
        ], $history);

        $sdk->sendMessage('conv1', ['content' => 'hi']);
        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame('bot', $body['sender_type']);
    }

    public function testAssignConversationSendsMemberId(): void
    {
        $history = [];
        $sdk = $this->sdk([
            $this->jsonResponse(200, ['ok' => true, 'data' => ['assigned' => true]]),
        ], $history);

        $sdk->assignConversation('conv1', 'm1');

        $request = $history[0]['request'];
        $this->assertSame('PATCH', $request->getMethod());
        $this->assertSame('/v1/conversations/conv1/assign', $request->getUri()->getPath());
        $this->assertSame('m1', json_decode((string) $request->getBody(), true)['member_id']);
    }

    public function testAssignConversationUnassignSendsNullMemberId(): void
    {
        $history = [];
        $sdk = $this->sdk([
            $this->jsonResponse(200, ['ok' => true, 'data' => ['assigned' => true]]),
        ], $history);

        $sdk->assignConversation('conv1', null);

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertArrayHasKey('member_id', $body);
        $this->assertNull($body['member_id']);
    }

    public function testUpsertContactPostsToContacts(): void
    {
        $history = [];
        $sdk = $this->sdk([
            $this->jsonResponse(200, ['ok' => true, 'data' => ['id' => 'c1']]),
        ], $history);

        $sdk->upsertContact(['external_id' => 'ext-1', 'name' => 'Alice']);

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/v1/contacts', $request->getUri()->getPath());
        $this->assertSame('ext-1', json_decode((string) $request->getBody(), true)['external_id']);
    }

    public function testGetAndListContacts(): void
    {
        $history = [];
        $sdk = $this->sdk([
            $this->jsonResponse(200, ['ok' => true, 'data' => ['id' => 'c1']]),
            $this->jsonResponse(200, ['ok' => true, 'data' => [['id' => 'c1']]]),
        ], $history);

        $sdk->getContact('c1');
        $sdk->listContacts(['limit' => 10]);

        $this->assertSame('/v1/contacts/c1', $history[0]['request']->getUri()->getPath());
        $this->assertSame('/v1/contacts', $history[1]['request']->getUri()->getPath());
        $this->assertSame('limit=10', $history[1]['request']->getUri()->getQuery());
    }

    public function testConversationLifecycleRoutes(): void
    {
        $history = [];
        $sdk = $this->sdk([
            $this->jsonResponse(200, ['ok' => true, 'data' => ['id' => 'conv1']]), // getConversation
            $this->jsonResponse(200, ['ok' => true, 'data' => []]), // listConversations
            $this->jsonResponse(200, ['ok' => true, 'data' => ['resolved' => true]]), // resolve
            $this->jsonResponse(200, ['ok' => true, 'data' => ['updated' => true]]), // updateConversation
            $this->jsonResponse(200, ['ok' => true, 'data' => ['taken_over' => true]]), // takeover
            $this->jsonResponse(200, ['ok' => true, 'data' => ['joined' => true]]), // join
            $this->jsonResponse(200, ['ok' => true, 'data' => ['left' => true]]), // leave
            $this->jsonResponse(200, ['ok' => true, 'data' => ['invited' => true]]), // invite
            $this->jsonResponse(200, ['ok' => true, 'data' => []]), // list notes
            $this->jsonResponse(201, ['ok' => true, 'data' => ['id' => 'n1']]), // add note
        ], $history);

        $sdk->getConversation('conv1');
        $sdk->listConversations(['status' => 'open']);
        $sdk->resolveConversation('conv1');
        $sdk->updateConversation('conv1', ['status' => 'open']);
        $sdk->takeoverConversation('conv1');
        $sdk->joinConversation('conv1');
        $sdk->leaveConversation('conv1');
        $sdk->inviteToConversation('conv1', ['member_id' => 'm1']);
        $sdk->listConversationNotes('conv1');
        $sdk->addConversationNote('conv1', ['content' => 'note']);

        $expected = [
            ['GET', '/v1/conversations/conv1'],
            ['GET', '/v1/conversations'],
            ['PATCH', '/v1/conversations/conv1/resolve'],
            ['PATCH', '/v1/conversations/conv1/workflow'],
            ['PATCH', '/v1/conversations/conv1/takeover'],
            ['PATCH', '/v1/conversations/conv1/join'],
            ['PATCH', '/v1/conversations/conv1/leave'],
            ['POST', '/v1/conversations/conv1/invite'],
            ['GET', '/v1/conversations/conv1/notes'],
            ['POST', '/v1/conversations/conv1/notes'],
        ];

        foreach ($expected as $i => [$method, $path]) {
            $this->assertSame($method, $history[$i]['request']->getMethod(), "method mismatch at index {$i}");
            $this->assertSame($path, $history[$i]['request']->getUri()->getPath(), "path mismatch at index {$i}");
        }
    }

    public function testIdentityAndWorkspaceRoutes(): void
    {
        $history = [];
        $sdk = $this->sdk([
            $this->jsonResponse(200, ['ok' => true, 'data' => ['id' => 'u1']]), // getMe
            $this->jsonResponse(200, ['ok' => true, 'data' => ['updated' => true]]), // updateMe
            $this->jsonResponse(200, ['ok' => true, 'data' => []]), // listMyWorkspaces
            $this->jsonResponse(200, ['ok' => true, 'data' => ['updated' => true]]), // updateMyAvailability
            $this->jsonResponse(200, ['ok' => true, 'data' => ['id' => 'ws1']]), // getWorkspace
        ], $history);

        $sdk->getMe();
        $sdk->updateMe(['name' => 'X']);
        $sdk->listMyWorkspaces();
        $sdk->updateMyAvailability('online');
        $sdk->getWorkspace();

        $expected = [
            ['GET', '/v1/me'],
            ['PATCH', '/v1/me'],
            ['GET', '/v1/me/workspaces'],
            ['PATCH', '/v1/me/availability'],
            ['GET', '/v1/workspace'],
        ];

        foreach ($expected as $i => [$method, $path]) {
            $this->assertSame($method, $history[$i]['request']->getMethod(), "method mismatch at index {$i}");
            $this->assertSame($path, $history[$i]['request']->getUri()->getPath(), "path mismatch at index {$i}");
        }
    }

    public function testBotsCannedAndApiKeysRoutes(): void
    {
        $history = [];
        $sdk = $this->sdk([
            $this->jsonResponse(200, ['ok' => true, 'data' => []]), // listBots
            $this->jsonResponse(201, ['ok' => true, 'data' => ['id' => 'b1']]), // createBot
            $this->jsonResponse(200, ['ok' => true, 'data' => ['updated' => true]]), // updateBot
            $this->jsonResponse(200, ['ok' => true, 'data' => ['deleted' => true]]), // deleteBot
            $this->jsonResponse(200, ['ok' => true, 'data' => []]), // listCannedResponses
            $this->jsonResponse(201, ['ok' => true, 'data' => ['id' => 'cr1']]), // createCannedResponse
            $this->jsonResponse(200, ['ok' => true, 'data' => ['updated' => true]]), // updateCannedResponse
            $this->jsonResponse(200, ['ok' => true, 'data' => ['deleted' => true]]), // deleteCannedResponse
            $this->jsonResponse(201, ['ok' => true, 'data' => ['id' => 'ak1']]), // createApiKey
            $this->jsonResponse(200, ['ok' => true, 'data' => []]), // listApiKeys
            $this->jsonResponse(200, ['ok' => true, 'data' => ['deleted' => true]]), // revokeApiKey
        ], $history);

        $sdk->listBots();
        $sdk->createBot(['name' => 'B']);
        $sdk->updateBot('b1', ['name' => 'B2']);
        $sdk->deleteBot('b1');
        $sdk->listCannedResponses();
        $sdk->createCannedResponse(['text' => 'hi']);
        $sdk->updateCannedResponse('cr1', ['text' => 'hey']);
        $sdk->deleteCannedResponse('cr1');
        $sdk->createApiKey(['name' => 'k']);
        $sdk->listApiKeys();
        $sdk->revokeApiKey('ak1');

        $expected = [
            ['GET', '/v1/bots'],
            ['POST', '/v1/bots'],
            ['PATCH', '/v1/bots/b1'],
            ['DELETE', '/v1/bots/b1'],
            ['GET', '/v1/canned-responses'],
            ['POST', '/v1/canned-responses'],
            ['PATCH', '/v1/canned-responses/cr1'],
            ['DELETE', '/v1/canned-responses/cr1'],
            ['POST', '/v1/api-keys'],
            ['GET', '/v1/api-keys'],
            ['DELETE', '/v1/api-keys/ak1'],
        ];

        foreach ($expected as $i => [$method, $path]) {
            $this->assertSame($method, $history[$i]['request']->getMethod(), "method mismatch at index {$i}");
            $this->assertSame($path, $history[$i]['request']->getUri()->getPath(), "path mismatch at index {$i}");
        }
    }

    public function testChannelWebhookAndConnectionRoutes(): void
    {
        $history = [];
        $sdk = $this->sdk([
            $this->jsonResponse(200, ['ok' => true, 'data' => ['updated' => true]]), // setup webhook
            $this->jsonResponse(200, ['ok' => true, 'data' => ['ok' => true]]), // test connection
        ], $history);

        $sdk->setupChannelWebhook('c1', ['url' => 'https://x.com']);
        $sdk->testChannelConnection('c1', ['url' => 'https://x.com']);

        $this->assertSame('POST', $history[0]['request']->getMethod());
        $this->assertSame('/v1/channels/c1/setup-webhook', $history[0]['request']->getUri()->getPath());
        $this->assertSame('POST', $history[1]['request']->getMethod());
        $this->assertSame('/v1/channels/c1/test-connection', $history[1]['request']->getUri()->getPath());
    }

    public function testConstructorCompatibilityWithoutApiKey(): void
    {
        $sdk = new ServerSDK('https://api.example.com');
        $this->assertInstanceOf(ServerSDK::class, $sdk);
    }
}
