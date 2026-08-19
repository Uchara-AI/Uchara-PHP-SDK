<?php

namespace Uchara\SDK;

use GuzzleHttp\Client;

/**
 * Agent SDK for building custom agent dashboards and automations.
 *
 * Authenticates workspace members with email/password JWTs from
 * /v1/auth/login. Messages sent through this SDK are attributed to the
 * authenticated member (sender_type=agent, sender_id=<member id>).
 */
class AgentSDK
{
    private HTTPClient $http;
    private ?string $accessToken = null;
    private ?string $refreshToken = null;

    /** @var array<string,mixed>|null */
    private ?array $member = null;

    /**
     * @param Client|null $client Optional Guzzle client (injectable for tests).
     */
    public function __construct(
        string $apiUrl,
        ?string $accessToken = null,
        int $timeout = 30,
        ?Client $client = null
    ) {
        $this->http = new HTTPClient($apiUrl, $timeout, $client);
        $this->http->setOnRefresh(function (): bool {
            if ($this->refreshToken === null || $this->refreshToken === '') {
                return false;
            }

            try {
                $this->refresh();
                return true;
            } catch (\Throwable) {
                return false;
            }
        });
        $this->http->setOnUnauthorized(function (): void {
            $this->accessToken = null;
            $this->http->setAuthToken('');
        });

        if ($accessToken !== null && $accessToken !== '') {
            $this->setAccessToken($accessToken);
        }
    }

    /**
     * Login an agent with email/password and store the returned JWTs.
     *
     * The workspace slug is optional when the email belongs to one workspace.
     * It is required by the API when the email belongs to multiple workspaces.
     *
     * @return array<string,mixed> API login payload (token, refresh_token, member, workspace)
     */
    public function login(string $email, string $password, ?string $workspaceSlug = null): array
    {
        $payload = [
            'email' => $email,
            'password' => $password,
        ];
        if ($workspaceSlug !== null && $workspaceSlug !== '') {
            $payload['slug'] = $workspaceSlug;
        }

        $response = $this->http->post('/v1/auth/login', $payload);
        $this->storeAuthResponse($response);

        return $response;
    }

    /**
     * Exchange the refresh token for a new access/refresh token pair.
     *
     * @return array<string,mixed>
     */
    public function refresh(?string $refreshToken = null): array
    {
        $refreshToken = $refreshToken ?? $this->refreshToken;
        if ($refreshToken === null || $refreshToken === '') {
            throw new UcharaException('A refresh token is required.', 0);
        }

        $response = $this->http->post('/v1/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);
        $this->storeAuthResponse($response);

        return $response;
    }

    public function setAccessToken(string $accessToken): void
    {
        $this->accessToken = $accessToken;
        $this->http->setAuthToken($accessToken);
    }

    public function setRefreshToken(string $refreshToken): void
    {
        $this->refreshToken = $refreshToken;
    }

    /**
     * Initialize this SDK from a token pair created by a backend ServerSDK.
     * No network request is made and no API key is accepted here.
     *
     * @param array<string,mixed> $response
     */
    public function loginWithToken(array $response): void
    {
        $this->storeAuthResponse($response);
    }

    /**
     * Alias for applications that prefer an explicit access-token name.
     *
     * @param array<string,mixed> $response
     */
    public function setSession(array $response): void
    {
        $this->loginWithToken($response);
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    /** @return array<string,mixed>|null */
    public function getMember(): ?array
    {
        return $this->member;
    }

    public function getMe(): array
    {
        return $this->http->get('/v1/me');
    }

    public function updateAvailability(string $availability): array
    {
        return $this->http->patch('/v1/me/availability', ['availability' => $availability]);
    }

    public function listConversations(array $filters = []): array
    {
        return $this->http->get('/v1/conversations', $filters);
    }

    public function getConversation(string $conversationId): array
    {
        return $this->http->get("/v1/conversations/{$conversationId}");
    }

    public function getMessages(string $conversationId, array $options = []): array
    {
        return $this->http->get("/v1/conversations/{$conversationId}/messages", $options);
    }

    /**
     * Send a message as the authenticated human agent.
     *
     * Do not pass sender_type or sender_id: the API derives both from the JWT.
     */
    public function sendMessage(string $conversationId, array $data): array
    {
        unset($data['sender_type'], $data['sender_id']);

        return $this->http->post("/v1/conversations/{$conversationId}/messages", $data);
    }

    public function assignConversation(string $conversationId, ?string $memberId = null): array
    {
        return $this->http->patch(
            "/v1/conversations/{$conversationId}/assign",
            ['member_id' => $memberId]
        );
    }

    public function updateWorkflow(string $conversationId, array $data): array
    {
        return $this->http->patch("/v1/conversations/{$conversationId}/workflow", $data);
    }

    public function resolveConversation(string $conversationId): array
    {
        return $this->http->patch("/v1/conversations/{$conversationId}/resolve");
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

    public function listNotes(string $conversationId): array
    {
        return $this->http->get("/v1/conversations/{$conversationId}/notes");
    }

    public function addNote(string $conversationId, array $data): array
    {
        return $this->http->post("/v1/conversations/{$conversationId}/notes", $data);
    }

    public function listContacts(array $options = []): array
    {
        return $this->http->get('/v1/contacts', $options);
    }

    public function getContact(string $contactId): array
    {
        return $this->http->get("/v1/contacts/{$contactId}");
    }

    public function listMembers(array $options = []): array
    {
        return $this->http->get('/v1/workspace/members', $options);
    }

    public function listChannels(): array
    {
        return $this->http->get('/v1/channels');
    }

    /**
     * @param array<string,mixed> $response
     */
    private function storeAuthResponse(array $response): void
    {
        // The current API calls the access token "token". Accept
        // "access_token" as well for compatibility with clients/proxies that
        // expose the more explicit name.
        $token = $response['token'] ?? $response['access_token'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new UcharaException('Authentication response did not contain an access token.', 0, $response);
        }

        $this->setAccessToken($token);

        $refreshToken = $response['refresh_token'] ?? null;
        if (is_string($refreshToken) && $refreshToken !== '') {
            $this->setRefreshToken($refreshToken);
        }

        $member = $response['member'] ?? null;
        $this->member = is_array($member) ? $member : null;
    }
}
