<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\Notifier;

class FakeNotifier implements Notifier
{
    /** @var array<int, array{title: string, message: string}> */
    public array $notifications = [];

    public function notify(string $title, string $message): void
    {
        $this->notifications[] = ['title' => $title, 'message' => $message];
    }
}
