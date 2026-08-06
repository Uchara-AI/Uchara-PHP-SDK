<?php

namespace Uchara\SDK;

use Exception;

/**
 * Structured exception thrown by the SDK.
 *
 * The HTTP status code is exposed both through the standard `getCode()` and the
 * more explicit `getStatus()` alias. `getDetails()` returns the parsed error
 * payload from the API (when available) and `getResponse()` returns the full
 * structured response for advanced inspection.
 */
class UcharaException extends Exception
{
    /** @var mixed */
    private $details;

    private ?UcharaResponse $response;

    /**
     * @param string               $message  Human readable error message
     * @param int                  $code     HTTP status code (0 for transport errors)
     * @param mixed                $details  Parsed error payload from the API
     * @param UcharaResponse|null  $response The structured response, when available
     */
    public function __construct(string $message, int $code = 0, $details = null, ?UcharaResponse $response = null)
    {
        parent::__construct($message, $code);
        $this->details = $details;
        $this->response = $response;
    }

    /**
     * @return mixed
     */
    public function getDetails()
    {
        return $this->details;
    }

    /**
     * Alias for getCode() — the HTTP status code.
     */
    public function getStatus(): int
    {
        return $this->getCode();
    }

    public function getResponse(): ?UcharaResponse
    {
        return $this->response;
    }
}
