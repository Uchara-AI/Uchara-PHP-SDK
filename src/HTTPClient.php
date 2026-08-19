<?php

namespace Uchara\SDK;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

/**
 * Thin, injectable HTTP client for the Uchara API.
 *
 * Responsibilities:
 *  - Build requests against a base URL with JSON headers and optional auth.
 *  - Send requests with a configurable Guzzle client (injectable for tests).
 *  - Parse the standard `{ok, data, meta, error}` envelope and unwrap `data`.
 *  - Surface structured errors as UcharaException with status + details.
 *  - Expose status/meta/headers via UcharaResponse for advanced callers.
 *
 * The simple helpers (get/post/patch/delete/put) return the unwrapped `data`
 * array for convenience; `request()` returns the full UcharaResponse.
 */
class HTTPClient
{
    private Client $client;
    private string $baseUrl;
    private ?string $authToken = null;

    /** @var callable|null */
    private $onUnauthorized;

    /** @var callable|null */
    private $onRefresh;

    /** @var array<string,string> */
    private array $defaultHeaders = [];

    /**
     * @param Client|null $client Optional Guzzle client. When omitted a default
     *                            client is created with the given timeout and
     *                            `http_errors` disabled so we can parse errors
     *                            ourselves. Injecting a client (e.g. one backed
     *                            by a MockHandler) is fully supported.
     */
    public function __construct(string $baseUrl, int $timeout = 30, ?Client $client = null)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->client = $client ?? new Client([
            'timeout' => $timeout,
            'http_errors' => false,
        ]);
    }

    public function setAuthToken(string $token): void
    {
        $this->authToken = $token;
    }

    public function setOnUnauthorized(?callable $callback): void
    {
        $this->onUnauthorized = $callback;
    }

    public function setOnRefresh(?callable $callback): void
    {
        $this->onRefresh = $callback;
    }

    public function setDefaultHeader(string $name, string $value): void
    {
        $this->defaultHeaders[$name] = $value;
    }

    public function removeDefaultHeader(string $name): void
    {
        unset($this->defaultHeaders[$name]);
    }

    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, ['query' => $query])->data();
    }

    public function post(string $path, ?array $body = null, array $headers = []): array
    {
        return $this->request('POST', $path, ['json' => $body, 'headers' => $headers])->data();
    }

    public function patch(string $path, ?array $body = null, array $headers = []): array
    {
        return $this->request('PATCH', $path, ['json' => $body, 'headers' => $headers])->data();
    }

    public function put(string $path, ?array $body = null, array $headers = []): array
    {
        return $this->request('PUT', $path, ['json' => $body, 'headers' => $headers])->data();
    }

    public function delete(string $path, array $headers = []): array
    {
        return $this->request('DELETE', $path, ['headers' => $headers])->data();
    }

    /**
     * Send a multipart/form-data upload (e.g. file uploads).
     *
     * @param array<int,array<string,mixed>> $multipart Guzzle multipart parts
     * @param array<string,string>           $headers   Extra request headers
     */
    public function upload(string $path, array $multipart, array $headers = []): array
    {
        return $this->request('POST', $path, ['multipart' => $multipart, 'headers' => $headers])->data();
    }

    /**
     * Perform a request and return the raw response body as a string.
     * Useful for non-JSON endpoints such as conversation downloads.
     */
    public function raw(string $method, string $path, array $options = []): string
    {
        $response = $this->request($method, $path, $options);

        return $response->rawBody() ?? '';
    }

    /**
     * Perform a request and return a structured UcharaResponse.
     *
     * Supported options:
     *  - query:     array of query string parameters
     *  - json:      body to be JSON encoded
     *  - headers:   per-request headers (merged over defaults)
     *  - multipart: Guzzle multipart parts
     *
     * @throws UcharaException on transport errors or non-2xx responses
     */
    public function request(string $method, string $path, array $options = [], bool $retry = true): UcharaResponse
    {
        $url = $this->baseUrl . $path;

        $headers = array_merge([
            'Accept' => 'application/json',
        ], $this->defaultHeaders);

        // For multipart requests Guzzle must set its own Content-Type (with the
        // boundary); for everything else default to JSON.
        if (empty($options['multipart'])) {
            $headers['Content-Type'] = 'application/json';
        }

        if ($this->authToken !== null) {
            $headers['Authorization'] = 'Bearer ' . $this->authToken;
        }

        if (!empty($options['headers']) && is_array($options['headers'])) {
            $headers = array_merge($headers, $options['headers']);
        }

        $requestOptions = $options;
        $requestOptions['headers'] = $headers;

        try {
            $response = $this->client->request($method, $url, $requestOptions);
            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();
            $responseHeaders = $response->getHeaders();

            // Treat any non-2xx status as an error (the API uses 201 for
            // created resources, so success is the whole 2xx range).
            if ($statusCode < 200 || $statusCode >= 300) {
                if (
                    $statusCode === 401
                    && $retry
                    && $path !== '/v1/auth/refresh'
                    && $this->onRefresh !== null
                    && (bool) call_user_func($this->onRefresh)
                ) {
                    return $this->request($method, $path, $options, false);
                }

                if ($statusCode === 401 && $this->onUnauthorized !== null) {
                    call_user_func($this->onUnauthorized);
                }

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

                throw new UcharaException(
                    $message,
                    $statusCode,
                    $details,
                    new UcharaResponse($statusCode, [], [], $responseHeaders, $body)
                );
            }

            // 204 No Content or an empty body.
            if ($statusCode === 204 || $body === '') {
                return new UcharaResponse($statusCode, [], [], $responseHeaders, $body);
            }

            $decoded = json_decode($body, true);

            // Non-JSON response: return the raw body for callers that need it.
            if (!is_array($decoded)) {
                return new UcharaResponse($statusCode, [], [], $responseHeaders, $body);
            }

            // Unwrap the standard {ok, data, meta, error} envelope.
            if (array_key_exists('ok', $decoded)) {
                if ($decoded['ok'] === false) {
                    $err = $decoded['error'] ?? [];
                    $message = is_array($err)
                        ? ($err['message'] ?? 'Request failed')
                        : (string) $err;

                    throw new UcharaException(
                        $message,
                        $statusCode,
                        $err,
                        new UcharaResponse($statusCode, [], [], $responseHeaders, $body)
                    );
                }

                $data = $decoded['data'] ?? [];
                $meta = $decoded['meta'] ?? [];

                return new UcharaResponse(
                    $statusCode,
                    is_array($data) ? $data : [],
                    is_array($meta) ? $meta : [],
                    $responseHeaders,
                    $body
                );
            }

            // Response without an envelope: return it as-is.
            return new UcharaResponse($statusCode, $decoded, [], $responseHeaders, $body);
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
