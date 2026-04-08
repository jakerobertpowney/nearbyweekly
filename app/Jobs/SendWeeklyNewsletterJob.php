<?php

namespace App\Jobs;

use App\Exceptions\NoMatchesException;
use App\Mail\WeeklyNewsletterMail;
use App\Models\NewsletterItem;
use App\Models\NewsletterRun;
use App\Models\User;
use App\Services\Events\NewsletterCurator;
use App\Services\Newsletter\SubjectLineGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendWeeklyNewsletterJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $newsletterRunId,
        public int $userId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(NewsletterCurator $curator, SubjectLineGenerator $generator): void
    {
        $run = NewsletterRun::query()->find($this->newsletterRunId);
        $user = User::query()->find($this->userId);

        if ($run === null || $user === null || ! $user->newsletter_enabled) {
            return;
        }

        try {
            $result = $curator->curate($user);
        } catch (NoMatchesException $e) {
            Log::info("Newsletter suppressed for user [{$user->id}]: {$e->getMessage()}", [
                'user_id' => $user->id,
                'run_id' => $run->id,
            ]);

            $run->update([
                'status' => 'no_matches',
                'sent_at' => now(),
            ]);

            return;
        }

        $buckets           = $result['buckets'];
        $seasonalPicks     = $result['seasonal_picks'] ?? [];
        $fallbackLevel     = $result['fallback_level'];
        $bucketSummary     = $result['bucket_summary'];
        $newsletterContext = $result['newsletter_context'] ?? [];

        foreach ($buckets as $bucket) {
            foreach ($bucket as $match) {
                NewsletterItem::query()->firstOrCreate([
                    'newsletter_run_id' => $run->id,
                    'user_id'           => $user->id,
                    'event_id'          => $match['event']->id,
                ], [
                    'ranking_score' => $match['score'],
                ]);
            }
        }

        foreach ($seasonalPicks as $pick) {
            NewsletterItem::query()->firstOrCreate([
                'newsletter_run_id' => $run->id,
                'user_id'           => $user->id,
                'event_id'          => $pick['event']->id,
            ], [
                'ranking_score' => null,
            ]);
        }

        $generatedSubject = $generator->generate($user, $buckets, $newsletterContext['day_type'] ?? 'normal');

        Mail::to($user)->send(new WeeklyNewsletterMail(
            $user,
            $buckets,
            $newsletterContext,
            $seasonalPicks,
            $generatedSubject,
        ));

        $run->update([
            'status' => 'sent',
            'sent_at' => now(),
            'fallback_level' => $fallbackLevel,
            'bucket_summary' => $bucketSummary,
        ]);
    }
}
