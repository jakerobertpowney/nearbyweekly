<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\Events\EventIngestionService;
use App\Services\Importers\BillettoImporter;
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
        $action = $payload['action'] ?? null;
        $data = $payload['data'] ?? [];

        match ($action) {
            'event.published', 'event.updated' => $this->handleUpsert($data),
            'event.unpublished', 'event.cancelled' => $this->handleCancel($data),
            default => null,
        };

        return response(null, 204);
    }

    private function handleUpsert(array $data): void
    {
        $importer = new BillettoImporter;
        $normalised = $importer->normalise($data);

        if ($normalised) {
            $this->ingestion->persistSingle($normalised);
        }
    }

    private function handleCancel(array $data): void
    {
        $externalId = (string) ($data['id'] ?? '');

        if ($externalId) {
            Event::where('source', 'billetto')
                ->where('external_id', $externalId)
                ->update(['status' => 'cancelled']);
        }
    }

    private function verifySignature(Request $request): bool
    {
        $header = $request->header('Billetto-Signature');

        if (! $header) {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            $segments = explode('=', trim($part), 2);
            if (count($segments) !== 2) {
                continue;
            }
            [$prefix, $value] = $segments;
            if ($prefix === 't') {
                $timestamp = $value;
            } elseif ($prefix === 'v1') {
                $signatures[] = $value;
            }
        }

        if (! $timestamp || empty($signatures)) {
            return false;
        }

        $signedPayload = $timestamp.'.'.$request->getContent();
        $expected = hash_hmac('sha256', $signedPayload, config('services.billetto.webhook_secret'));

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                if (abs(time() - (int) $timestamp) > 300) {
                    return false;
                }

                return true;
            }
        }

        return false;
    }
}
