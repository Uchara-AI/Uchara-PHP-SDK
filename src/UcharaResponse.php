<?php

namespace Uchara\SDK;

/**
 * A structured HTTP response returned by the HTTPClient.
 *
 * It exposes the HTTP status code, the unwrapped `data` payload, the
 * pagination/`meta` envelope (when present), the response headers and the raw
 * body. This lets callers inspect status and meta without losing information
 * that the simple array-returning helpers discard.
 */
class UcharaResponse
{
    /**
     * @param int         $status   HTTP status code
     * @param array       $data     Unwrapped `data` payload (or the whole body when no envelope)
     * @param array       $meta     Pagination / metadata envelope
     * @param array       $headers  Response headers
     * @param string|null $rawBody  Raw response body
     */
    public function __construct(
        private int $status,
        private array $data = [],
        private array $meta = [],
        private array $headers = [],
        private ?string $rawBody = null,
    ) {
    }

    public function status(): int
    {
        return $this->status;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function data(): array
    {
        return $this->data;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function meta(): array
    {
        return $this->meta;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function rawBody(): ?string
    {
        return $this->rawBody;
    }

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * Convenience alias returning the unwrapped data payload.
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
