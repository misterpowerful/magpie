<?php

declare(strict_types=1);

namespace App\Notifiers;

use App\Contracts\Notifier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Spec section 2: Pushover first (one-time purchase, nothing to
 * host). Silently skips (logging a warning) rather than throwing when
 * unconfigured, so a missing Pushover account never fails a run — the
 * notification is a nice-to-have, not part of the pipeline's own
 * correctness.
 */
final class PushoverNotifier implements Notifier
{
    public function notify(string $title, string $message): void
    {
        $token = config('services.pushover.token');
        $userKey = config('services.pushover.user_key');

        if (! $token || ! $userKey) {
            Log::warning('Pushover is not configured — skipping notification', ['title' => $title]);

            return;
        }

        try {
            Http::asForm()->post('https://api.pushover.net/1/messages.json', [
                'token' => $token,
                'user' => $userKey,
                'title' => $title,
                'message' => $message,
            ])->throw();
        } catch (\Throwable $e) {
            Log::error('Pushover notification failed', ['title' => $title, 'error' => $e->getMessage()]);
        }
    }
}
