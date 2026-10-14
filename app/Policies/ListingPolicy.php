<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;

/**
 * Single source of truth for listing ownership checks — previously a
 * route-binding check inside UpdateListingStatusRequest::authorize().
 */
final class ListingPolicy
{
    public function update(User $user, Listing $listing): bool
    {
        return $user->id === $listing->user_id;
    }
}
