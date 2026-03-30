<?php

namespace App\Services\Events;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiCategoryClassifier
{
    /**
     * Classify an event into an interest using the Anthropic API.
     *
     * Returns the matching interest ID, or null if nothing fits or the API is unavailable.
     *
     * @param  array<int, array{id: int, slug: string, name: string}>  $interests
     */
    public function classify(string $title, string $description, string $category, array $interests): ?int
    {
        if (empty(config('services.anthropic.api_key'))) {
            return null;
        }

        $interestList = collect($interests)
            ->map(fn (array $i): string => "{$i['slug']}: {$i['name']}")
            ->implode("\n");

        $prompt = <<<PROMPT
            You are classifying an event into one of the available interest categories.

            Event title: {$title}
            Event description: {$description}
            Event category slug: {$category}

            Available interests (slug: name):
            {$interestList}

            Return ONLY a JSON object with a single key "interest" containing the slug of the best matching interest, or null if none fit.
            Example: {"interest": "music"} or {"interest": null}
            PROMPT;

        try {
            $response = Http::baseUrl(config('services.anthropic.base_url'))
                ->withHeaders([
                    'x-api-key' => config('services.anthropic.api_key'),
                    'anthropic-version' => '2023-06-01',
                ])
                ->acceptJson()
                ->post('/v1/messages', [
                    'model' => 'claude-haiku-4-5-20251001',
                    'max_tokens' => 64,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('AiCategoryClassifier: API request failed.', [
                    'status' => $response->status(),
                    'category' => $category,
                ]);

                return null;
            }

            $text = data_get($response->json(), 'content.0.text', '');
            $text = preg_replace('/^```(?:json)?\s*/i', '', trim((string) $text));
            $text = preg_replace('/\s*```$/', '', $text);
            $decoded = json_decode($text, true);

            if (! is_array($decoded) || ! array_key_exists('interest', $decoded)) {
                Log::warning('AiCategoryClassifier: unexpected response format.', [
                    'text' => $text,
                    'category' => $category,
                ]);

                return null;
            }

            $slug = $decoded['interest'];

            if ($slug === null) {
                return null;
            }

            $match = collect($interests)->firstWhere('slug', $slug);

            return $match['id'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('AiCategoryClassifier: exception during classification.', [
                'error' => $e->getMessage(),
                'category' => $category,
            ]);

            return null;
        }
    }
}
