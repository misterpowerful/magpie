<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Spec section 9/10: live run progress on the dashboard, scoped to the
// owning user (single-tenant today, but still per-user from day one).
Broadcast::channel('runs.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
