<?php

namespace Uchara\SDK;

use GuzzleHttp\Client;

/**
 * Visitor SDK for embedding the chat widget in customer applications.
 *
 * Wraps the public /v1/widget/* REST endpoints. Call `init()` first to create a
 * visitor session and obtain a visitor JWT; subsequent authenticated calls
 * (active conversation, messages, upload, download, close) use that token.
 */
class VisitorSDK
{
    private HTTPClient $http;
    private string $widgetToken;
    private ?string $visitorToken = null;
    private ?string $contactId = null;

    /**
     * @param Client|null $client Optional Guzzle client (injectable for tests).
     */
    public function __construct(string $apiUrl, string $widgetToken, int $timeout = 30, ?Client $client = null)
    {
        $this->widgetToken = $widgetToken;
        $this->http = new HTTPClient($apiUrl, $timeout, $client);
    }

    /**
     * Create a visitor session and store the returned visitor JWT.
     *
     * @param array $contact Optional visitor identity fields:
     *                       external_id, name, email, phone.
     */
    public function init(array $contact = []): array
    {
        $payload = array_merge(['widget_token' => $this->widgetToken], $contact);

        $response = $this->http->post('/v1/widget/session', $payload);

        $this->visitorToken = $response['visitor_token'] ?? null;
        $this->contactId = $response['contact_id'] ?? null;

        if ($this->visitorToken !== null) {
            $this->http->setAuthToken($this->visitorToken);
        }

        return $response;
    }

    /**
     * Get public widget configuration (channel name + widget config).
     */
    public function getConfig(): array
    {
        return $this->http->get('/v1/widget/config', ['token' => $this->widgetToken]);
    }

    /**
     * Get the active conversation for this visitor, or null when none exists.
     */
    public function getActiveConversation(): ?array
    {
        try {
            return $this->http->get('/v1/widget/conversations/active');
        } catch (UcharaException $e) {
            if ($e->getStatus() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Start a new conversation.
     */
    public function startConversation(array $payload = []): array
    {
        return $this->http->post('/v1/widget/conversations', $payload);
    }

    /**
     * Get messages for a conversation.
     *
     * @param array $options Optional query params (limit, offset).
     */
    public function getMessages(string $conversationId, array $options = []): array
    {
        return $this->http->get(
            "/v1/widget/conversations/{$conversationId}/messages",
            $options
        );
    }

    /**
     * Send a message as the visitor.
     *
     * @param array $payload Message payload (content is required).
     */
    public function sendMessage(string $conversationId, array $payload): array
    {
        return $this->http->post(
            "/v1/widget/conversations/{$conversationId}/messages",
            $payload
        );
    }

    /**
     * Upload a file to a conversation (multipart/form-data).
     *
     * @param string      $conversationId Conversation id
     * @param string      $filePath       Absolute path to the file
     * @param string|null $filename       Optional filename (defaults to basename)
     * @param string|null $mimeType       Optional MIME type (auto-detected when omitted)
     */
    public function upload(string $conversationId, string $filePath, ?string $filename = null, ?string $mimeType = null): array
    {
        if (!is_file($filePath)) {
            throw new UcharaException("File not found: {$filePath}", 0);
        }

        $filename = $filename ?? basename($filePath);
        $mimeType = $mimeType ?? (function_exists('mime_content_type')
            ? (mime_content_type($filePath) ?: 'application/octet-stream')
            : 'application/octet-stream');

        $multipart = [[
            'name' => 'file',
            'contents' => fopen($filePath, 'r'),
            'filename' => $filename,
            'headers' => ['Content-Type' => $mimeType],
        ]];

        return $this->http->upload(
            "/v1/widget/conversations/{$conversationId}/uploads",
            $multipart
        );
    }

    /**
     * Download the conversation transcript as plain text.
     */
    public function download(string $conversationId): string
    {
        return $this->http->raw(
            'GET',
            "/v1/widget/conversations/{$conversationId}/download"
        );
    }

    /**
     * Close the visitor's own conversation.
     */
    public function close(string $conversationId): array
    {
        return $this->http->post("/v1/widget/conversations/{$conversationId}/close");
    }

    /**
     * Alias for close().
     */
    public function closeConversation(string $conversationId): array
    {
        return $this->close($conversationId);
    }

    public function getVisitorToken(): ?string
    {
        return $this->visitorToken;
    }

    public function getContactId(): ?string
    {
        return $this->contactId;
    }
}
