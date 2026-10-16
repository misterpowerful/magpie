<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\EmailPullRequestService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('magpie:run-requested-email-pull')]
#[Description('Run the email pull if the dashboard button has requested one')]
class RunRequestedEmailPull extends Command
{
    public function handle(EmailPullRequestService $requests): int
    {
        $user = User::admin();

        if (! $user) {
            $this->error(User::MISSING_ADMIN_MESSAGE);

            return self::FAILURE;
        }

        if ($requests->runIfRequested($user->id)) {
            $this->info('Requested email pull finished.');
        }

        return self::SUCCESS;
    }
}
