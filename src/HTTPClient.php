<?php

namespace Uchara\SDK;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

class HTTPClient
{
    private Client $client;
    private string $baseUrl;
    private ?string $authToken = null;

    public function __construct(string $baseUrl, int $timeout = 30)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->client = new Client([
            'timeout' => $timeout,
            'http_errors' => false,
        ]);
    }

    public function setAuthToken(string $token): void
    {
        $this->authToken = $token;
    }

    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, ['query' => $query]);
    }

    public function post(string $path, $body = null): array
    {
        return $this->request('POST', $path, ['json' => $body]);
    }

    public function patch(string $path, $body = null): array
    {
        return $this->request('PATCH', $path, ['json' => $body]);
    }

    public function delete(string $path): array
    {
        return $this->request('DELETE', $path);
    }

    private function request(string $method, string $path, array $options = []): array
    {
        $url = $this->baseUrl . $path;

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($this->authToken !== null) {
            $headers['Authorization'] = 'Bearer ' . $this->authToken;
        }

        $options['headers'] = $headers;

        try {
            $response = $this->client->request($method, $url, $options);
            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();

            // Treat any non-2xx status as an error (the API uses 201 for
            // created resources, so success is the whole 2xx range).
            if ($statusCode < 200 || $statusCode >= 300) {
                $decoded = json_decode($body, true);
                $err = is_array($decoded) ? ($decoded['error'] ?? null) : null;

                $message = $response->getReasonPhrase();
                $details = null;
                if (is_array($err)) {
                    $message = $err['message'] ?? $message;
                    $details = $err;
                } elseif ($err !== null) {
                    $message = (string) $err;
                }

                throw new UcharaException($message, $statusCode, $details);
            }

            // 204 No Content or an empty body.
            if ($statusCode === 204 || $body === '') {
                return [];
            }

            $decoded = json_decode($body, true);

            // Non-JSON response: handle gracefully instead of failing.
            if (!is_array($decoded)) {
                return [];
            }

            // Unwrap the standard {ok, data, meta, error} envelope.
            if (array_key_exists('ok', $decoded)) {
                if ($decoded['ok'] === false) {
                    $err = $decoded['error'] ?? [];
                    $message = is_array($err)
                        ? ($err['message'] ?? 'Request failed')
                        : (string) $err;

                    throw new UcharaException($message, $statusCode, $err);
                }

                $data = $decoded['data'] ?? [];
                return is_array($data) ? $data : [];
            }

            // Response without an envelope: return it as-is.
            return $decoded;
        } catch (UcharaException $e) {
            throw $e;
        } catch (RequestException $e) {
            throw new UcharaException(
                'Network error: ' . $e->getMessage(),
                $e->getCode()
            );
        } catch (GuzzleException $e) {
            throw new UcharaException(
                'HTTP error: ' . $e->getMessage(),
                0
            );
        }
    }
}
