<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * An exception that carries an HTTP status code.
 *
 * Controllers throw this for expected failures (400, 404, 409, ...). The
 * Application catches it and turns it into a clean JSON error response instead
 * of a PHP crash.
 */
final class HttpException extends RuntimeException
{
    public function __construct(
        private int $status,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }
}
