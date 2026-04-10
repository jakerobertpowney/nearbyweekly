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

        // Flatten all buckets, sort by score descending, take top 5.
        $topEvents = collect($buckets)
            ->flatten(1)
            ->sortByDesc('score')
            ->take(5)
            ->values();

        if ($topEvents->isEmpty()) {
            return $fallback;
        }

        $outwardCode = explode(' ', trim($postcode))[0];

        $totalCount = collect($buckets)->flatten(1)->count();

        $eventLines = $topEvents->map(function (array $match): string {
            $event    = $match['event'];
            $venue    = $event->venue ?? 'unknown venue';
            $category = $event->category ?? 'event';

            return "- {$event->title} | {$category} | {$venue}";
        })->implode("\n");

        $prompt = <<<PROMPT
You are writing the subject line for a personalised weekly events newsletter called Eventaroo.

Inputs:
- User's postcode area: {$outwardCode}
- Total matched events in this newsletter: {$totalCount}

Top matched events (title | category | venue):
{$eventLines}

Rules:
- Write one subject line only — no explanation, no alternatives, no punctuation at the end
- Lead with the most interesting or specific event title from the list — this does more work than any label
- Never mention the day of the week — the email may be read days after it was sent
- Do not use a fixed postcode prefix like "Near {$outwardCode}:" or "Your {$outwardCode}" — if you reference location at all, weave it in naturally (e.g. "happening near you" or "in {$outwardCode} this week")
- Keep it under 50 characters where possible — subject lines are truncated on mobile
- You may include one emoji if it naturally fits the lead event's category (e.g. 🎸 for a gig, 🎨 for an art show, 🍕 for a food event) — do not add an emoji just to add one, and never use more than one
- Tone should be warm and editorial — like a recommendation from a friend, not a marketing blast
- Avoid generic filler phrases like "Don't miss out", "Check this out", or "This week's picks"

Examples of good subject lines:
Inja at Joshua Brooks and 4 more things near you
🎸 Inja at Joshua Brooks + 4 more this week
Silverstone track day, comedy in Stockport, and more
🎨 Three exhibitions worth leaving the house for

Examples of bad subject lines:
Your {$outwardCode} Friday: Inja at Joshua Brooks + more  ← uses day of week, postcode prefix feels robotic
Don't miss these amazing events near you this week!  ← generic, no specificity
🎉🎸🍕 This week in {$outwardCode}  ← emoji overload, no lead event

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

            $content = preg_replace('/^```(?:json)?\s*/i', '', trim($content));
            $content = preg_replace('/\s*```$/', '', $content);

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
