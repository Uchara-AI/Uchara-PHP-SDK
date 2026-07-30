<?php

namespace Uchara\SDK;

use Exception;

class UcharaException extends Exception
{
    private $details;

    public function __construct(string $message, int $code = 0, $details = null)
    {
        parent::__construct($message, $code);
        $this->details = $details;
    }

    public function getDetails()
    {
        return $this->details;
    }
}
