<?php

declare(strict_types=1);

namespace App\Http\Controllers\Actions\EmailIntake;

use App\Services\EmailPullRequestService;
use Illuminate\Http\JsonResponse;

final class RunEmailIntakeAction
{
    public function __construct(
        private readonly EmailPullRequestService $requests,
    ) {}

    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => $this->requests->request()], 202);
    }
}
