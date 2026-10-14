<?php

declare(strict_types=1);

namespace App\Http\Controllers\Actions\Health;

use Illuminate\Http\JsonResponse;

final class PingAction
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['ok' => true]);
    }
}
