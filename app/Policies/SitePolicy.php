<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Site;
use App\Models\User;

/**
 * Single source of truth for site ownership checks — previously split
 * across FormRequest::authorize() (update) and inline abort_unless()
 * calls (destroy/run) in the old SiteController.
 */
final class SitePolicy
{
    public function update(User $user, Site $site): bool
    {
        return $user->id === $site->user_id;
    }

    public function delete(User $user, Site $site): bool
    {
        return $user->id === $site->user_id;
    }

    public function run(User $user, Site $site): bool
    {
        return $user->id === $site->user_id;
    }
}
