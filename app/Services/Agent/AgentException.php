<?php

namespace App\Services\Agent;

use RuntimeException;

/** An update-agent failure, with a short machine code and whether trying again later may help. */
class AgentException extends RuntimeException
{
    public function __construct(string $message, public string $errorCode = 'error', public bool $retryable = false, public ?int $status = null)
    {
        parent::__construct($message);
    }
}
