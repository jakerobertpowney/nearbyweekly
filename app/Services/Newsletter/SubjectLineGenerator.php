<?php

namespace App\Services\Newsletter;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SubjectLineGenerator
{
    /**
     * Generate a personalised subject line for the weekly newsletter.
     *
     * Falls back to a static day-type string if the API is unavailable or
     * not configured.
     *
     * @param  array<string, list<array{event: \App\Models\Event, score: float}>>  $buckets
     */
    public function generate(User $user, array $buckets, string $dayType): string
    {
        $postcode = $user->postcode;

        $fallback = match ($dayType) {
            'saturday' => "Still happening near {$postcode} this weekend",
            'sunday'   => "Plan your week — events near {$postcode}",
            'friday'   => "This weekend near {$postcode} — what's on",
            default    => "Your weekly events near {$postcode}",
        };

        $apiKey = config('services.anthropic.api_key');

        if (empty($apiKey)) {
            return $fallback;
        }

        // Flatten all buckets, sort by score descending, take top 3.
        $topEvents = collect($buckets)
            ->flatten(1)
            ->sortByDesc('score')
            ->take(3)
            ->values();

        if ($topEvents->isEmpty()) {
            return $fallback;
        }

        $eventLines = $topEvents->map(function (array $match): string {
            $event = $match['event'];
            $date  = $event->starts_at->format('j M');

            return "- {$event->title} ({$date})";
        })->implode("\n");

        $prompt = <<<PROMPT
You are writing the subject line for a personalised weekly events newsletter.

User's postcode: {$postcode}
Day type: {$dayType}

Top events this week:
{$eventLines}

Write a subject line that:
- Is under 60 characters
- References at least one specific event title or category from the list above
- Contains no emoji and no exclamation marks
- Feels personal and local, not generic

Respond with only a JSON object in this exact format:
{"subject": "your subject line here"}
PROMPT;

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->post(rtrim(config('services.anthropic.base_url'), '/') . '/v1/messages', [
                'model'      => 'claude-haiku-4-5-20251001',
                'max_tokens' => 128,
                'messages'   => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            if (! $response->successful()) {
                Log::warning('SubjectLineGenerator: API request failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return $fallback;
            }

            $content = $response->json('content.0.text');

            if (! is_string($content)) {
                Log::warning('SubjectLineGenerator: unexpected response shape', [
                    'body' => $response->body(),
                ]);

                return $fallback;
            }

            $decoded = json_decode($content, true);

            if (! is_array($decoded) || empty($decoded['subject'])) {
                Log::warning('SubjectLineGenerator: could not parse subject from response', [
                    'content' => $content,
                ]);

                return $fallback;
            }

            return (string) $decoded['subject'];
        } catch (\Throwable $e) {
            Log::warning('SubjectLineGenerator: exception during generation', [
                'error' => $e->getMessage(),
            ]);

            return $fallback;
        }
    }
}
