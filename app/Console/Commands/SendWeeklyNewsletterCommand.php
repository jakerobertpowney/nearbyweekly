<?php

namespace App\Console\Commands;

use App\Jobs\SendWeeklyNewsletterJob;
use App\Models\NewsletterRun;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('newsletters:send-weekly')]
#[Description('Queue the weekly personalized newsletter for subscribed users')]
class SendWeeklyNewsletterCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $run = NewsletterRun::query()->create([
            'scheduled_for' => now(),
            'sent_at' => now(),
            'status' => 'queued',
        ]);

        $users = User::query()
            ->where('newsletter_enabled', true)
            ->whereNotNull('postcode')
            ->whereHas('interests')
            ->get();

        foreach ($users as $user) {
            SendWeeklyNewsletterJob::dispatch($run->id, $user->id);
        }

        $this->info("Queued {$users->count()} weekly newsletter jobs.");

        return self::SUCCESS;
    }
}
