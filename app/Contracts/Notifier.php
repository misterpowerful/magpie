<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Spec section 2 "Notifications": Pushover first, Web Push later if
 * wanted — both would implement this same contract.
 */
interface Notifier
{
    public function notify(string $title, string $message): void;
}
