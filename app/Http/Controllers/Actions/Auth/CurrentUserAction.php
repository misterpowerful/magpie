<?php

declare(strict_types=1);

namespace App\Http\Controllers\Actions\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

final class CurrentUserAction
{
    public function __invoke(Request $request): ?Authenticatable
    {
        return $request->user();
    }
}
