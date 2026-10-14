<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Assigns a correlation id to every request — generated fresh, or
 * trusted from an inbound X-Correlation-Id header — so a single id can
 * tie a client-visible error (see ApiErrorRenderer) back to server logs.
 */
final class AssignCorrelationId
{
    public const HEADER = 'X-Correlation-Id';

    public const ATTRIBUTE = 'correlation_id';

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $correlationId = $request->header(self::HEADER) ?: (string) Str::uuid();

        $request->attributes->set(self::ATTRIBUTE, $correlationId);

        /** @var SymfonyResponse $response */
        $response = $next($request);
        $response->headers->set(self::HEADER, $correlationId);

        return $response;
    }
}
