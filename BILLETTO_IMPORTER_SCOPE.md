# Billetto Importer Scope

## What Billetto Is

Billetto is a self-service event ticketing platform operating across 14 European markets including
a dedicated UK site at billetto.co.uk. Unlike Ticketmaster (large-scale commercial events) or
Data Thistle (curated lifestyle listings), Billetto sits squarely in the community and independent
event space: workshops, yoga retreats, local markets, comedy nights, art exhibitions, speed dating,
whiskey tastings, volunteer days, fundraisers, and grassroots festivals. Free-ticket events are
genuinely common on the platform — it doesn't charge fees for free entry events — which means
the event mix is meaningfully different from what Ticketmaster and Skiddle provide.

This integration has two complementary parts: a **scheduled importer** (daily batch fetch) and
an **inbound webhook handler** (near-real-time updates). Build both.

---

## Authentication

Billetto uses an **API keypair** — a key and a secret that are concatenated with `:` and passed
in the `Api-Keypair` request header. Both values are required; neither is usable alone.

```
Api-Keypair: YourAPIKey:YourAPISecret
```

The keypair is obtained from the Billetto organiser dashboard under **Integrate → Developers**.
The secret is only shown once at generation time — it cannot be retrieved again.

**`config/services.php`:**

```php
'billetto' => [
    'api_key'        => env('BILLETTO_API_KEY'),
    'api_secret'     => env('BILLETTO_API_SECRET'),
    'webhook_secret' => env('BILLETTO_WEBHOOK_SECRET'),
],
```

**`.env.example`** — add:

```
BILLETTO_API_KEY=
BILLETTO_API_SECRET=
BILLETTO_WEBHOOK_SECRET=
```

`isConfigured()` on the importer must check that **both** key and secret are present:

```php
public function isConfigured(): bool
{
    return filled(config('services.billetto.api_key'))
        && filled(config('services.billetto.api_secret'));
}
```

---

## Part 1 — `BillettoImporter` (Scheduled Batch Fetch)

### Base URL and Endpoint

From the official documentation, the confirmed endpoint is:

```
GET https://billetto.dk/api/v3/public/events
```

> **Note for Claude Code:** The docs use `billetto.dk` as the base domain. Verify whether a
> `billetto.co.uk/api/v3/public/events` variant exists for UK-specific results, or whether the
> country filter parameter on the `.dk` endpoint is sufficient to restrict to UK events.
> Use whichever the API docs confirm returns GB-market events.

### Request Headers

```php
Http::withHeaders([
    'Api-Keypair' => config('services.billetto.api_key') . ':' . config('services.billetto.api_secret'),
])
```

### Query Parameters

| Parameter | Value for NearbyWeekly |
|---|---|
| `limit` | `100` (confirmed in docs example) |
| `country` | `GB` (verify param name against live docs) |
| `start_date` | Today's date ISO 8601 |
| `end_date` | 90 days from now ISO 8601 |

Exclude online events at query time if a filter param exists, otherwise filter in `normalise()`
by checking `$raw['online_event'] === true` — online events have no physical venue and cannot be
matched to users by postcode.

> **Note for Claude Code:** Confirm the pagination mechanism (cursor vs page number) and the
> country/locale filter param name from the live reference docs before implementing.

### Pagination

The docs confirm `limit` as a parameter. After each response, check the response envelope for a
`next` cursor or `meta.last_page` integer and loop until exhausted. Use a defensive maximum page
cap (e.g. 50 pages × 100 = 5,000 events) to protect against runaway loops on first run.

### `BillettoImporter` Class

Create `app/Services/Events/Importers/BillettoImporter.php`:

```php
<?php

namespace App\Services\Events\Importers;

use App\Contracts\EventImporter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BillettoImporter implements EventImporter
{
    private const BASE_URL = 'https://billetto.dk/api/v3/public/events';
    private const MAX_PAGES = 50;

    public function name(): string
    {
        return 'billetto';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.billetto.api_key'))
            && filled(config('services.billetto.api_secret'));
    }

    public function fetch(array $options = []): array
    {
        $events  = [];
        $page    = 1;
        $hasMore = true;

        while ($hasMore && $page <= self::MAX_PAGES) {
            $response = Http::withHeaders([
                'Api-Keypair' => config('services.billetto.api_key')
                    . ':' . config('services.billetto.api_secret'),
            ])->get(self::BASE_URL, [
                'limit'      => 100,
                'country'    => 'GB',          // verify param name
                'start_date' => now()->toIso8601String(),
                'end_date'   => now()->addDays(90)->toIso8601String(),
                'page'       => $page,         // verify pagination param
            ]);

            if (! $response->successful()) {
                Log::warning('BillettoImporter: request failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                    'page'   => $page,
                ]);
                break;
            }

            $pageEvents = $response->json('data', []);  // verify envelope key
            $events     = array_merge($events, array_filter(
                array_map(fn (array $raw) => $this->normalise($raw), $pageEvents)
            ));

            // Verify pagination shape against live docs and adjust accordingly
            $hasMore = $response->json('meta.current_page', 1)
                < $response->json('meta.last_page', 1);

            $page++;
        }

        return $events;
    }

    public function normalise(array $raw): ?array
    {
        // Skip online-only events — no physical venue to match against users
        if (! empty($raw['online_event'])) {
            return null;
        }

        // Confirmed field path from API resource docs: venue.location.postal_code
        $location = $raw['venue']['location'] ?? [];
        $postcode  = $location['postal_code'] ?? null;

        // Fall back to regex extraction from address string if postal_code is absent/blank
        if (! $postcode) {
            $addressText = implode(' ', array_filter([
                $location['address_line_1'] ?? null,
                $location['city']           ?? null,
            ]));
            $postcode = $this->extractPostcode($addressText);
        }

        if (! $postcode) {
            return null; // cannot match to users without a postcode
        }

        // Derive price range from ticket_types list (each ticket type has a string `price`)
        $prices = collect($raw['ticket_types']['data'] ?? [])
            ->pluck('price')
            ->map(fn ($p) => (float) $p)
            ->filter(fn ($p) => $p >= 0);

        return [
            'source'      => 'billetto',
            'external_id' => (string) $raw['id'],
            'title'       => $raw['name'],               // confirmed: field is `name` not `title`
            'description' => strip_tags($raw['description'] ?? $raw['description_html'] ?? ''),
            'category'    => $raw['category'] ?? null,   // confirmed: flat string e.g. "music"
            'starts_at'   => $raw['starts_at'],          // confirmed: `starts_at`
            'ends_at'     => $raw['ends_at'] ?? null,    // confirmed: `ends_at`
            'venue_name'  => $raw['venue']['name'] ?? null,
            'address'     => trim(implode(', ', array_filter([
                $location['address_line_1'] ?? null,
                $location['city']           ?? null,
            ]))),
            'postcode'    => strtoupper(trim($postcode)),
            'latitude'    => $location['latitude']  ?? null,  // confirmed: venue.location.latitude
            'longitude'   => $location['longitude'] ?? null,  // confirmed: venue.location.longitude
            'url'         => $raw['public_url'],              // confirmed: `public_url`
            'image_url'   => null,                            // verify if image field exists on public endpoint
            'price_min'   => $prices->min(),
            'price_max'   => $prices->max(),
        ];
    }

    private function extractPostcode(string $text): ?string
    {
        preg_match('/[A-Z]{1,2}[0-9][0-9A-Z]?\s?[0-9][A-Z]{2}/i', $text, $matches);
        return $matches[0] ?? null;
    }
}
```

### Registration in `EventIngestionService`

Add `BillettoImporter` to the importers array. It must run independently — a Billetto failure
must not block Data Thistle or Ticketmaster. Because `isConfigured()` returns false when either
key or secret is absent, the importer silently skips without a guard clause in the service.

```php
new BillettoImporter(),
```

---

## Part 2 — Webhook Handler (Real-Time Updates)

The scheduled importer runs daily and provides bulk coverage. The webhook handler complements it
by processing individual event changes in near real-time: a newly published event appears in the
database within seconds of the organiser hitting publish, and cancelled events can be suppressed
immediately rather than lingering until the next daily run.

### Relevant Webhook Actions

From the docs, the following `action` values are relevant to NearbyWeekly:

| Action | What to do |
|---|---|
| `event.published` | Fetch the full event from the API and persist it (same as normalise + persist) |
| `event.updated` | Fetch the updated event and update the existing DB row |
| `event.unpublished` | Set `status = cancelled` or delete the event row |
| `event.cancelled` | Set `status = cancelled` or delete the event row |
| `event.completed` | Mark as past — no action needed beyond letting it age out naturally |

All others (`order.*`, `attendee.*`, `ledger_entry.*`) can be acknowledged and ignored.

### Webhook Route

Add to `routes/web.php`:

```php
Route::post('/webhooks/billetto', BillettoWebhookController::class)
    ->name('webhooks.billetto')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
```

CSRF must be excluded — this is an inbound server-to-server POST, not a browser form submission.
Alternatively, add `/webhooks/*` to the `$except` array in `VerifyCsrfToken.php`.

### Signature Verification

Every inbound webhook carries a `Billetto-Signature` header:

```
Billetto-Signature: t=1592390250, v1=47a52d61c2fe35cf05d7805703cd091d37815939f374b3cd2a956c9b7bb5d327
```

Verification must happen **before** any payload processing. Reject and return `400` on failure.

```php
private function verifySignature(Request $request): bool
{
    $header = $request->header('Billetto-Signature');

    if (! $header) {
        return false;
    }

    // Parse: "t=1592390250, v1=abc123..."
    $parts     = [];
    $timestamp = null;
    $signatures = [];

    foreach (explode(',', $header) as $part) {
        [$prefix, $value] = explode('=', trim($part), 2);
        if ($prefix === 't') {
            $timestamp = $value;
        } elseif ($prefix === 'v1') {
            $signatures[] = $value;
        }
    }

    if (! $timestamp || empty($signatures)) {
        return false;
    }

    // Signed payload = timestamp + '.' + raw request body
    $signedPayload = $timestamp . '.' . $request->getContent();

    // HMAC-SHA256 using the webhook secret
    $expected = hash_hmac('sha256', $signedPayload, config('services.billetto.webhook_secret'));

    // Constant-time comparison to prevent timing attacks
    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            // Optional: reject if timestamp is older than 5 minutes
            if (abs(time() - (int) $timestamp) > 300) {
                return false;
            }
            return true;
        }
    }

    return false;
}
```

### `BillettoWebhookController`

Create `app/Http/Controllers/BillettoWebhookController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Services\Events\EventIngestionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class BillettoWebhookController extends Controller
{
    public function __construct(
        private readonly EventIngestionService $ingestion,
    ) {}

    public function __invoke(Request $request): Response
    {
        if (! $this->verifySignature($request)) {
            Log::warning('BillettoWebhook: invalid signature');
            return response('Unauthorized', 401);
        }

        $payload = $request->json()->all();
        $action  = $payload['action'] ?? null;
        $data    = $payload['data']   ?? [];

        match ($action) {
            'event.published', 'event.updated' => $this->handleUpsert($data),
            'event.unpublished', 'event.cancelled' => $this->handleCancel($data),
            default => null, // acknowledge and ignore
        };

        // Return 204 — Billetto ignores response body
        return response(null, 204);
    }

    private function handleUpsert(array $data): void
    {
        // Normalise the event payload from the webhook data object
        // The webhook data shape mirrors the REST API event object
        $importer   = new \App\Services\Events\Importers\BillettoImporter();
        // normalise() is now public — confirmed webhook data shape mirrors the REST event resource
        $normalised = $importer->normalise($data);

        if ($normalised) {
            $this->ingestion->persistSingle($normalised);
        }
    }

    private function handleCancel(array $data): void
    {
        $externalId = (string) ($data['id'] ?? '');

        if ($externalId) {
            \App\Models\Event::where('source', 'billetto')
                ->where('external_id', $externalId)
                ->update(['status' => 'cancelled']);
        }
    }

    private function verifySignature(Request $request): bool
    {
        // ... (implementation above)
    }
}
```

> **Note for Claude Code:** `EventIngestionService` currently has a `persist()` method that
> accepts an array of events. Add a `persistSingle(array $event): void` method that calls the
> existing persist logic for a single normalised event, to avoid rehydrating all events for one
> webhook call. `BillettoImporter::normalise()` is declared `public` in this scope so the
> webhook controller can call it directly without duplication.

### Webhook Registration

Once the endpoint is deployed and accessible, the Billetto webhook URL is registered in the
organiser dashboard under **Integrate → Developers → Webhooks**. Register:

```
https://yourdomain.co.uk/webhooks/billetto
```

Subscribe to: `event.published`, `event.updated`, `event.unpublished`, `event.cancelled`.

The `BILLETTO_WEBHOOK_SECRET` value comes from the secret shown when creating the webhook in the
dashboard — the same one used for signature verification.

---

## Category Mappings

Add a `billetto` block to `CategoryMappingSeeder`. The resource docs confirm `category` is a
**flat snake_case string** on the event object (e.g. `"auto_boat"`, `"music"`), not a nested
object. The `external_category` values must match these raw API strings exactly.

The table below is a starting point based on Billetto's public category list. **After wiring up
the importer, make a test fetch, log all distinct `category` values returned for GB events, and
reconcile this table against real output before seeding production.** Any unmapped categories will
fall through to the AI classifier (step 12 in CLAUDE.md) automatically.

| interest slug | source | external_category |
|---|---|---|
| `music` | `billetto` | `music` |
| `music` | `billetto` | `festival` |
| `arts-and-culture` | `billetto` | `art` |
| `arts-and-culture` | `billetto` | `visual_art` |
| `arts-and-culture` | `billetto` | `exhibition` |
| `theatre` | `billetto` | `theatre` |
| `comedy` | `billetto` | `comedy` |
| `food-and-drink` | `billetto` | `food_drink` |
| `food-and-drink` | `billetto` | `food` |
| `wellness` | `billetto` | `health_wellness` |
| `wellness` | `billetto` | `yoga` |
| `fitness` | `billetto` | `sport` |
| `fitness` | `billetto` | `fitness` |
| `family-days-out` | `billetto` | `family_kids` |
| `family-days-out` | `billetto` | `kids` |
| `networking` | `billetto` | `business` |
| `networking` | `billetto` | `conference` |

---

## Incremental Fetch Consideration

Billetto's public search API is date-window based rather than incremental. The recommended approach
is to always fetch from today + 90 days. This is safe to run daily since
`EventIngestionService::persist()` deduplicates on `source + external_id`. The webhook handler
covers the gap between daily runs for new and changed events.

---

## Verification Steps After Implementation

1. Run `php artisan events:fetch` and check `ingestion_logs` for a `billetto` row with
   `status = success` and `fetched > 0`
2. Run `php artisan events:list --source=billetto` and inspect a sample — confirm `postcode`,
   `category`, and `starts_at` are populated
3. Log the distinct `category` string values returned (flat field, e.g. `"music"`, `"art"`) and reconcile against the seeder table above
4. Test the webhook endpoint locally with a tool like ngrok + Billetto's test webhook delivery
   in the dashboard — confirm `204` response and that the signature check passes
5. Publish a test event on a sandbox Billetto account and confirm it appears in the `events`
   table within seconds via the webhook, without waiting for the next daily fetch
6. Run `php artisan matches:preview {user}` for a user with relevant interests to confirm
   Billetto events surface in the matcher
