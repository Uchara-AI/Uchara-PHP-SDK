<?php

namespace Uchara\SDK;

use GuzzleHttp\Client;

/**
 * Server SDK for server-to-server integration with the Uchara API.
 *
 * Authenticates with a Server SDK API key (uchara_sk_...) and wraps the
 * authenticated /v1/* REST endpoints. All methods return the unwrapped `data`
 * payload as a PHP array.
 *
 * Members are the human users of a workspace. Because many users refer to them
 * as "agents", ergonomic aliases (listAgents/getAgent/createAgent/...) are
 * provided alongside the canonical member methods.
 */
class ServerSDK
{
    private HTTPClient $http;

    /**
     * @param Client|null $client Optional Guzzle client (injectable for tests).
     */
    public function __construct(string $apiUrl, ?string $apiKey = null, int $timeout = 30, ?Client $client = null)
    {
        $this->http = new HTTPClient($apiUrl, $timeout, $client);

        if ($apiKey !== null) {
            $this->setApiKey($apiKey);
        }
    }

    public function setApiKey(string $apiKey): void
    {
        $this->http->setAuthToken($apiKey);
    }

    /**
     * Return the underlying HTTP client (useful for advanced callers that need
     * status/meta via UcharaResponse).
     */
    public function http(): HTTPClient
    {
        return $this->http;
    }

    // ── Identity ───────────────────────────────────────────────────────────────

    public function getMe(): array
    {
        return $this->http->get('/v1/me');
    }

    public function updateMe(array $data): array
    {
        return $this->http->patch('/v1/me', $data);
    }

    public function listMyWorkspaces(): array
    {
        return $this->http->get('/v1/me/workspaces');
    }

    public function updateMyAvailability(string $availability): array
    {
        return $this->http->patch('/v1/me/availability', ['availability' => $availability]);
    }

    // ── Workspace ──────────────────────────────────────────────────────────────

    public function getWorkspace(): array
    {
        return $this->http->get('/v1/workspace');
    }

    // ── Contacts ────────────────────────────────────────────────────────────────

    public function upsertContact(array $data): array
    {
        return $this->http->post('/v1/contacts', $data);
    }

    public function getContact(string $contactId): array
    {
        return $this->http->get("/v1/contacts/{$contactId}");
    }

    public function listContacts(array $options = []): array
    {
        return $this->http->get('/v1/contacts', $options);
    }

    // ── Conversations ───────────────────────────────────────────────────────────

    public function getConversation(string $conversationId): array
    {
        return $this->http->get("/v1/conversations/{$conversationId}");
    }

    public function listConversations(array $filters = []): array
    {
        return $this->http->get('/v1/conversations', $filters);
    }

    public function assignConversation(string $conversationId, ?string $agentId): array
    {
        return $this->http->patch(
            "/v1/conversations/{$conversationId}/assign",
            ['member_id' => $agentId]
        );
    }

    public function resolveConversation(string $conversationId): array
    {
        return $this->http->patch("/v1/conversations/{$conversationId}/resolve");
    }

    public function updateConversation(string $conversationId, array $data): array
    {
        return $this->http->patch("/v1/conversations/{$conversationId}/workflow", $data);
    }

    public function takeoverConversation(string $conversationId): array
    {
        return $this->http->patch("/v1/conversations/{$conversationId}/takeover");
    }

    public function joinConversation(string $conversationId): array
    {
        return $this->http->patch("/v1/conversations/{$conversationId}/join");
    }

    public function leaveConversation(string $conversationId): array
    {
        return $this->http->patch("/v1/conversations/{$conversationId}/leave");
    }

    public function inviteToConversation(string $conversationId, array $data): array
    {
        return $this->http->post("/v1/conversations/{$conversationId}/invite", $data);
    }

    public function listConversationNotes(string $conversationId): array
    {
        return $this->http->get("/v1/conversations/{$conversationId}/notes");
    }

    public function addConversationNote(string $conversationId, array $data): array
    {
        return $this->http->post("/v1/conversations/{$conversationId}/notes", $data);
    }

    // ── Messages ────────────────────────────────────────────────────────────────

    public function sendMessage(string $conversationId, array $data): array
    {
        if (!isset($data['sender_type'])) {
            $data['sender_type'] = 'bot';
        }

        return $this->http->post(
            "/v1/conversations/{$conversationId}/messages",
            $data
        );
    }

    public function getMessages(string $conversationId, array $options = []): array
    {
        return $this->http->get(
            "/v1/conversations/{$conversationId}/messages",
            $options
        );
    }

    // Message aliases — some users prefer more explicit names.
    public function sendMessageToConversation(string $conversationId, array $data): array
    {
        return $this->sendMessage($conversationId, $data);
    }

    public function listMessages(string $conversationId, array $options = []): array
    {
        return $this->getMessages($conversationId, $options);
    }

    public function getConversationMessages(string $conversationId, array $options = []): array
    {
        return $this->getMessages($conversationId, $options);
    }

    // ── Channels ────────────────────────────────────────────────────────────────

    public function listChannels(): array
    {
        return $this->http->get('/v1/channels');
    }

    /**
     * The backend exposes a list endpoint but no dedicated GET-by-id route, so
     * this filters the list (mirroring the TypeScript SDK behaviour).
     */
    public function getChannel(string $channelId): array
    {
        $channels = $this->listChannels();
        foreach ($channels as $channel) {
            if (($channel['id'] ?? null) === $channelId) {
                return $channel;
            }
        }

        throw new UcharaException("Channel {$channelId} not found", 404);
    }

    public function createChannel(array $data): array
    {
        return $this->http->post('/v1/channels', $data);
    }

    public function updateChannel(string $channelId, array $data): array
    {
        return $this->http->patch("/v1/channels/{$channelId}", $data);
    }

    public function deleteChannel(string $channelId): array
    {
        return $this->http->delete("/v1/channels/{$channelId}");
    }

    public function setupChannelWebhook(string $channelId, array $data = []): array
    {
        return $this->http->post("/v1/channels/{$channelId}/setup-webhook", $data);
    }

    public function testChannelConnection(string $channelId, array $data = []): array
    {
        return $this->http->post("/v1/channels/{$channelId}/test-connection", $data);
    }

    // ── Members (human workspace users) ─────────────────────────────────────────

    /**
     * List workspace members.
     *
     * @param array $query Optional query parameters (e.g. role, limit, offset).
     */
    public function listMembers(array $query = []): array
    {
        return $this->http->get('/v1/workspace/members', $query);
    }

    public function getMember(string $memberId): array
    {
        return $this->http->get("/v1/workspace/members/{$memberId}");
    }

    /**
     * Create a member.
     *
     * @param array       $payload         Member payload (e.g. email, name, role)
     * @param string|null $idempotencyKey  Optional idempotency key sent as the
     *                                     `Idempotency-Key` header so retries do
     *                                     not create duplicate members.
     */
    public function createMember(array $payload, ?string $idempotencyKey = null): array
    {
        $headers = [];
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return $this->http->post('/v1/workspace/members', $payload, $headers);
    }

    public function updateMember(string $memberId, array $payload): array
    {
        return $this->http->patch("/v1/workspace/members/{$memberId}", $payload);
    }

    /**
     * Compatibility route for updating a member's role.
     */
    public function updateMemberRole(string $memberId, string $role): array
    {
        return $this->http->patch(
            "/v1/workspace/members/{$memberId}/role",
            ['role' => $role]
        );
    }

    public function deactivateMember(string $memberId): array
    {
        return $this->http->post("/v1/workspace/members/{$memberId}/deactivate");
    }

    public function reactivateMember(string $memberId): array
    {
        return $this->http->post("/v1/workspace/members/{$memberId}/reactivate");
    }

    public function deleteMember(string $memberId): array
    {
        return $this->http->delete("/v1/workspace/members/{$memberId}");
    }

    // ── Invites ─────────────────────────────────────────────────────────────────

    public function inviteMember(array $payload): array
    {
        return $this->http->post('/v1/workspace/invites', $payload);
    }

    public function listInvites(): array
    {
        return $this->http->get('/v1/workspace/invites');
    }

    public function revokeInvite(string $inviteId): array
    {
        return $this->http->delete("/v1/workspace/invites/{$inviteId}");
    }

    // ── Agent aliases (members are commonly called "agents") ────────────────────

    public function listAgents(array $query = []): array
    {
        return $this->listMembers($query);
    }

    public function getAgent(string $agentId): array
    {
        return $this->getMember($agentId);
    }

    public function createAgent(array $payload, ?string $idempotencyKey = null): array
    {
        return $this->createMember($payload, $idempotencyKey);
    }

    public function updateAgent(string $agentId, array $payload): array
    {
        return $this->updateMember($agentId, $payload);
    }

    public function updateAgentRole(string $agentId, string $role): array
    {
        return $this->updateMemberRole($agentId, $role);
    }

    public function deactivateAgent(string $agentId): array
    {
        return $this->deactivateMember($agentId);
    }

    public function reactivateAgent(string $agentId): array
    {
        return $this->reactivateMember($agentId);
    }

    public function deleteAgent(string $agentId): array
    {
        return $this->deleteMember($agentId);
    }

    public function inviteAgent(array $payload): array
    {
        return $this->inviteMember($payload);
    }

    // ── Bots ────────────────────────────────────────────────────────────────────

    public function listBots(): array
    {
        return $this->http->get('/v1/bots');
    }

    public function createBot(array $data): array
    {
        return $this->http->post('/v1/bots', $data);
    }

    public function updateBot(string $botId, array $data): array
    {
        return $this->http->patch("/v1/bots/{$botId}", $data);
    }

    public function deleteBot(string $botId): array
    {
        return $this->http->delete("/v1/bots/{$botId}");
    }

    // ── Canned responses ────────────────────────────────────────────────────────

    public function listCannedResponses(): array
    {
        return $this->http->get('/v1/canned-responses');
    }

    public function createCannedResponse(array $data): array
    {
        return $this->http->post('/v1/canned-responses', $data);
    }

    public function updateCannedResponse(string $id, array $data): array
    {
        return $this->http->patch("/v1/canned-responses/{$id}", $data);
    }

    public function deleteCannedResponse(string $id): array
    {
        return $this->http->delete("/v1/canned-responses/{$id}");
    }

    // ── API Keys ────────────────────────────────────────────────────────────────

    /**
     * Create an API key. The raw key value is returned only once, in this
     * response, and cannot be retrieved again later.
     */
    public function createApiKey(array $data): array
    {
        return $this->http->post('/v1/api-keys', $data);
    }

    public function listApiKeys(): array
    {
        return $this->http->get('/v1/api-keys');
    }

    public function revokeApiKey(string $keyId): void
    {
        $this->http->delete("/v1/api-keys/{$keyId}");
    }
}
