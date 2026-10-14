<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * The additive fields every API error response carries, alongside
 * whatever shape the underlying exception already produces (e.g.
 * Laravel's native `message`/`errors` keys for a ValidationException —
 * see ApiErrorRenderer). Nothing existing is renamed or removed.
 */
final readonly class ApiError
{
    public function __construct(
        public string $correlationId,
        public string $code,
        public string $message,
    ) {}

    /**
     * @return array{correlationId: string, code: string, message: string}
     */
    public function toArray(): array
    {
        return [
            'correlationId' => $this->correlationId,
            'code' => $this->code,
            'message' => $this->message,
        ];
    }
}
