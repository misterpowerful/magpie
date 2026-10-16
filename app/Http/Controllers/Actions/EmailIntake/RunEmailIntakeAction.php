<?php

declare(strict_types=1);

namespace App\Http\Controllers\Actions\EmailIntake;

use App\Services\EmailIntakeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RunEmailIntakeAction
{
    public function __construct(
        private readonly EmailIntakeService $service,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $result = $this->service->run($request->user()->id);

        return response()->json(['data' => $result]);
    }
}
