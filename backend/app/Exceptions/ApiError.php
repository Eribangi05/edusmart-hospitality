<?php

namespace App\Exceptions;

use Exception;

class ApiError extends Exception
{
    public function __construct(public string $errorCode, string $message, public int $status = 400, public array $details = [])
    {
        parent::__construct($message);
    }
}
