<?php

namespace Uchara\SDK;

class ServerSDK
{
    private HTTPClient $http;

    public function __construct(string $apiUrl, ?string $apiKey = null, int $timeout = 30)
    {
        $this->http = new HTTPClient($apiUrl, $timeout);

        if ($apiKey !== null) {
            $this->setApiKey($apiKey);
        }
    }

    public function setApiKey(string $apiKey): void
    {
        $this->http->setAuthToken($apiKey);
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
            ['assigned_to' => $agentId]
        );
    }

    public function resolveConversation(string $conversationId): array
    {
        return $this->http->patch("/v1/conversations/{$conversationId}/resolve");
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

    // ── Channels ────────────────────────────────────────────────────────────────

    public function listChannels(): array
    {
        return $this->http->get('/v1/channels');
    }

    // ── Members ─────────────────────────────────────────────────────────────────

    public function listMembers(): array
    {
        return $this->http->get('/v1/workspace/members');
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
