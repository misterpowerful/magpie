<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Http\Middleware\AssignCorrelationId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Maps exceptions to the unified JSON error envelope. Additive only:
 * a ValidationException still gets Laravel's native `message`/`errors`
 * keys (so existing tests and the frontend's `e.response.data.errors`
 * reads are untouched) — `correlationId` and `code` are added alongside.
 */
final class ApiErrorRenderer
{
    public static function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! ($request->is('api/*') || $request->expectsJson())) {
            return null;
        }

        $correlationId = (string) ($request->attributes->get(AssignCorrelationId::ATTRIBUTE) ?? Str::uuid());

        if ($e instanceof ValidationException) {
            $error = new ApiError($correlationId, 'validation_failed', $e->getMessage());

            return new JsonResponse(
                [...$error->toArray(), 'errors' => $e->errors()],
                $e->status,
                [AssignCorrelationId::HEADER => $correlationId],
            );
        }

        if ($e instanceof AuthenticationException) {
            return self::respond($correlationId, 'unauthenticated', $e->getMessage(), 401);
        }

        if ($e instanceof AuthorizationException) {
            return self::respond($correlationId, 'forbidden', $e->getMessage() ?: 'This action is unauthorized.', 403);
        }

        if ($e instanceof ModelNotFoundException) {
            return self::respond($correlationId, 'not_found', $e->getMessage(), 404);
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();

            return self::respond($correlationId, self::codeForStatus($status), $e->getMessage() ?: self::defaultMessageForStatus($status), $status);
        }

        Log::error('Unhandled exception', [
            'correlation_id' => $correlationId,
            'exception' => $e,
        ]);

        return self::respond($correlationId, 'server_error', 'Something went wrong. Please try again.', 500);
    }

    private static function respond(string $correlationId, string $code, string $message, int $status): JsonResponse
    {
        $error = new ApiError($correlationId, $code, $message);

        return new JsonResponse($error->toArray(), $status, [AssignCorrelationId::HEADER => $correlationId]);
    }

    private static function codeForStatus(int $status): string
    {
        return match ($status) {
            401 => 'unauthenticated',
            403 => 'forbidden',
            404 => 'not_found',
            422 => 'validation_failed',
            default => $status >= 500 ? 'server_error' : 'client_error',
        };
    }

    private static function defaultMessageForStatus(int $status): string
    {
        return match ($status) {
            401 => 'Unauthenticated.',
            403 => 'This action is unauthorized.',
            404 => 'Not found.',
            default => $status >= 500 ? 'Something went wrong. Please try again.' : 'The request could not be processed.',
        };
    }
}
