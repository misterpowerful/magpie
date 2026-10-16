<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\EmailIntakeService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('magpie:process-emails')]
#[Description('Poll the inbox label and turn alert emails into listing stubs (spec section 5 "email")')]
class ProcessEmailIntake extends Command
{
    public function handle(EmailIntakeService $emailIntake): int
    {
        $user = User::admin();

        if (! $user) {
            $this->error(User::MISSING_ADMIN_MESSAGE);

            return self::FAILURE;
        }

        $result = $emailIntake->run($user->id);

        $this->info("Processed: {$result['processed']}, stubs: {$result['stubs']}, failed: {$result['failed']}");

        return self::SUCCESS;
    }
}
