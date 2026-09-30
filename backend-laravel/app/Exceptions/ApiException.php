<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * An expected business or security outcome with a machine-readable code (spec Appendix B).
 * Rendered by ApiExceptionRenderer; never reported to the error log.
 */
class ApiException extends RuntimeException
{
    /**
     * @param  list<array{field: string|null, code: string, message: string}>  $errors
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
        public readonly array $errors = [],
        public readonly mixed $data = null,
    ) {
        parent::__construct($message);
    }
}
