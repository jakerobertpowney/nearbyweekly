<?php

namespace App\Http\Controllers;

use App\Models\NewsletterRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    /**
     * Show the post-signup waiting page.
     */
    public function show(Request $request): Response
    {
        return Inertia::render('Welcome/Waiting', [
            'newsletterRunId' => $request->session()->get('newsletterRunId'),
            'email' => $request->session()->get('successEmail'),
        ]);
    }

    /**
     * Return the status of a newsletter run for polling.
     */
    public function status(Request $request): JsonResponse
    {
        $runId = $request->integer('run_id');

        if (! $runId) {
            return response()->json(['status' => 'unknown']);
        }

        $run = NewsletterRun::query()->find($runId);

        if (! $run) {
            return response()->json(['status' => 'unknown']);
        }

        return response()->json(['status' => $run->status]);
    }
}
