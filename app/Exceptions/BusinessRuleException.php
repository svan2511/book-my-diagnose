<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Domain/business-rule violation. Rendered as a 422 JSON error
 * without leaking internals (dev-prd §46, §72).
 */
class BusinessRuleException extends RuntimeException
{
    public function __construct(string $message = 'This action is not allowed.', private array $errors = [])
    {
        parent::__construct($message);
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
