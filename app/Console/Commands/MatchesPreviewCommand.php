<?php

namespace App\Console\Commands;

use App\Exceptions\NoMatchesException;
use App\Models\User;
use App\Services\Events\EventMatcher;
use App\Services\Events\NewsletterCurator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('matches:preview {email : User email address} {--curated : Route through NewsletterCurator and show time-bucketed results}')]
#[Description('Preview matched events for a specific subscriber')]
class MatchesPreviewCommand extends Command
{
    public function handle(EventMatcher $eventMatcher, NewsletterCurator $curator): int
    {
        $user = User::query()->where('email', (string) $this->argument('email'))->firstOrFail();

        if ($this->option('curated')) {
            return $this->showCurated($user, $curator);
        }

        $matches = $eventMatcher->forUser($user, 8);

        $this->table(['Event', 'Category', 'City', 'Distance', 'Score'], $matches->map(fn (array $match): array => [
            $match['event']->title,
            $match['event']->category,
            $match['event']->city,
            "{$match['distance_miles']} miles",
            $match['score'],
        ])->all());

        return self::SUCCESS;
    }

    private function showCurated(User $user, NewsletterCurator $curator): int
    {
        try {
            $result = $curator->curate($user);
        } catch (NoMatchesException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $bucketLabels = $result['newsletter_context']['bucket_labels'];
        $buckets      = $result['buckets'];

        foreach (['weekend', 'week', 'coming_soon'] as $key) {
            $label  = $bucketLabels[$key] ?? strtoupper($key);
            $events = $buckets[$key] ?? [];

            $this->newLine();
            $this->line("<fg=yellow;options=bold>── {$label} (" . count($events) . ' events)</>'  );

            if (empty($events)) {
                $this->line('  (none)');
                continue;
            }

            $rows = array_map(fn (array $m): array => [
                mb_strimwidth((string) $m['event']->title, 0, 45, '…'),
                $m['event']->category,
                $m['event']->city,
                "{$m['distance_miles']} mi",
                $m['score'],
                $m['match_type'],
                $m['event']->starts_at->format('D d M'),
            ], $events);

            $this->table(['Event', 'Category', 'City', 'Dist', 'Score', 'Type', 'Date'], $rows);
        }

        $this->newLine();
        $this->line('<fg=cyan>Interest distribution (by parent group):</>');

        $byInterest = $result['bucket_summary']['by_interest'] ?? [];

        if (empty($byInterest)) {
            $this->line('  (no interest data)');
        } else {
            $this->table(['Interest Group', 'Events'], array_map(
                fn (string $slug, int $count): array => [$slug, $count],
                array_keys($byInterest),
                array_values($byInterest),
            ));
        }

        $fallbackLevel = $result['fallback_level'];

        if ($fallbackLevel > 0) {
            $this->warn("Fallback level {$fallbackLevel} used (radius expanded to {$user->radius_miles * 1.5} miles).");
        }

        $this->newLine();
        $this->line(sprintf(
            '<fg=green>Total: %d events  |  Day type: %s</>',
            array_sum(array_map('count', $buckets)),
            $result['newsletter_context']['day_type'],
        ));

        return self::SUCCESS;
    }
}
