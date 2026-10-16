<?php

declare(strict_types=1);

namespace App\Http\Controllers\Actions\Runs;

use App\Services\RunRequestService;
use Illuminate\Http\JsonResponse;

final class RequestRunAction
{
    public function __construct(
        private readonly RunRequestService $requests,
    ) {}

    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => $this->requests->request()], 202);
    }
}
