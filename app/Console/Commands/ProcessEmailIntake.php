<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Inbox\EmailParserRegistry;
use App\Inbox\MailSource;
use App\Models\User;
use App\Services\EmailIntakeService;
use App\Services\ListingIntakeService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('magpie:process-emails')]
#[Description('Poll the inbox label and turn alert emails into listing stubs (spec section 5 "email")')]
class ProcessEmailIntake extends Command
{
    public function handle(MailSource $mailSource, EmailParserRegistry $registry, ListingIntakeService $listingIntake): int
    {
        $user = User::where('email', config('magpie.admin_email'))->first();

        if (! $user) {
            $this->error('No admin user found — check ADMIN_EMAIL and run the seeder.');

            return self::FAILURE;
        }

        $service = new EmailIntakeService($mailSource, $registry, $listingIntake, $user->id);
        $result = $service->run();

        $this->info("Processed: {$result['processed']}, stubs: {$result['stubs']}, failed: {$result['failed']}");

        return self::SUCCESS;
    }
}
