<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Support\Facades\Log;
use RuntimeException;

final class UserAdministrationException extends RuntimeException
{
    /**
     * @param array<string, string|null> $context
     */
    public function __construct(
        public readonly string $incidentId,
        public readonly array  $context,
    )
    {
        parent::__construct(__('users.errors.unexpected', ['incident' => $incidentId]));
    }

    /**
     * Report only the allow-listed incident context.
     */
    public function report(): void
    {
        Log::error('User administration operation failed.', $this->context);
    }
}
