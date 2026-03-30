# Scope: Web Risk Domain Safety Checking

## Context

The schema.org crawler (`scope-schema-org-crawler.md`) deliberately omits an automated malicious URL check. Domains are discovered through Web Data Commons — pre-indexed by Google's own crawler — making the realistic threat surface very low. The existing probe steps (keyword blocklist, RTA label, event markup verification) are sufficient for that discovery path.

This scope covers the point at which a malicious URL check becomes necessary: **when manual domain submission is introduced**, allowing venue operators to submit their own site for crawling. At that point, the input is untrusted and an automated safety check is no longer optional.

---

## Trigger for This Work

Build this scope when either of the following is true:

- A public-facing "Submit your venue" feature is added to Eventaroo
- The admin panel gains the ability to add domains to `external_websites` manually, and that feature will be accessible to anyone beyond the core team

Until then, this scope sits dormant. The `blocked_reason = 'malicious'` value is already reserved in the `external_websites` schema from the crawler scope — no migration changes are needed when this work is picked up.

---

## Why Web Risk, Not Safe Browsing

Google Safe Browsing API is restricted to non-commercial use. Eventaroo earns affiliate revenue from ticket clicks, which makes it a commercial product. **Google Web Risk API** is the correct choice — it covers the same threat types under a commercial licence with a free tier of 1,000 lookups/day, well above what manual submissions would generate even at scale.

---

## What Needs Building

### 1. Config Entry

```php
// config/services.php — google entry
'google' => [
    'web_risk_key' => env('GOOGLE_WEB_RISK_KEY'),
],
```

### 2. `WebRiskChecker` Service

Create `app/Services/Crawl/WebRiskChecker.php`.

**Constructor:** accepts an HTTP client (use Laravel's `Http` facade internally). No other dependencies.

**Single public method:** `isSafe(string $url): bool`

- Calls the Web Risk Lookup API:
  ```
  GET https://webrisk.googleapis.com/v1/uris:search
      ?uri={encoded_url}
      &threatTypes=MALWARE
      &threatTypes=SOCIAL_ENGINEERING
      &threatTypes=UNWANTED_SOFTWARE
      &key={config('services.google.web_risk_key')}
  ```
- If the response contains a non-empty `threat` object, returns `false` (unsafe)
- If the response has no `threat` key, returns `true` (safe)
- On any HTTP error, timeout, or malformed response: log a warning and return `true` — fail open rather than blocking legitimate venues due to an API outage
- If `GOOGLE_WEB_RISK_KEY` is not configured, log a warning and return `true` — the check degrades gracefully without crashing the probe

**Why fail open:** A false negative (missing a genuinely malicious domain) is far less harmful than a false positive (blocking a legitimate venue's submission). Manual submissions also receive human review before going live in any admin flow, providing a second check.

### 3. Inject into `ProbeExternalWebsiteJob`

Add a new step between the current Step 2 (TLD fast-track) and Step 3 (RTA label check). Call it **Step 2b** during the transition, then renumber to Step 3 and shift the existing steps when this scope is merged.

**New step — Web Risk check:**

- Skip if the domain ends in a trusted TLD (`.ac.uk`, `.gov.uk`, `.nhs.uk`, `.police.uk`, `.sch.uk`) — same fast-track logic as today
- Call `WebRiskChecker::isSafe('https://' . $website->domain)`
- If unsafe, call `$website->markBlocked('malicious')` and return
- If safe (or if the check is unavailable), continue to the next step

This step runs **only for manually submitted domains**. The probe already knows the submission source via `$website->discovery_source`. Add a guard:

```php
if ($website->discovery_source === 'manual') {
    // run Web Risk check
}
```

WDC and CC Index-sourced domains skip this step entirely — they continue through the probe unchanged, preserving the behaviour specified in the crawler scope.

### 4. Update Domain Activation Flow Table

When this scope is built, update the domain activation flow table in `scope-schema-org-crawler.md` to replace the placeholder row:

| Outcome | `crawl_status` | `blocked_reason` | Action required |
|---|---|---|---|
| Web Risk threat detected (manual submission) | `blocked` | `malicious` | None |

### 5. Bind in `AppServiceProvider`

```php
$this->app->bind(WebRiskChecker::class, fn () => new WebRiskChecker);
```

No interface needed at this stage — `WebRiskChecker` is a thin wrapper and not a candidate for swapping in tests. In tests, mock the `Http` facade directly.

---

## Files Affected

| File | Change |
|---|---|
| `config/services.php` | Add `google.web_risk_key` |
| `app/Services/Crawl/WebRiskChecker.php` | **Create** |
| `app/Jobs/ProbeExternalWebsiteJob.php` | **Update** — inject `WebRiskChecker`, add conditional step for manual submissions |
| `app/Providers/AppServiceProvider.php` | **Update** — bind `WebRiskChecker` |
| `docs/scope-schema-org-crawler.md` | **Update** — replace placeholder row in domain activation flow table |

No migrations required — `blocked_reason = 'malicious'` is already a valid value.

---

## Testing

- Mock `Http` to return a Web Risk response with a populated `threat` object. Assert `ProbeExternalWebsiteJob` sets `crawl_status = 'blocked'` and `blocked_reason = 'malicious'` for a `discovery_source = 'manual'` domain.
- Mock `Http` to return a response with no `threat` key. Assert the probe continues past the Web Risk step.
- Mock `Http` to throw a connection exception. Assert `isSafe()` returns `true` and the probe continues — fail open confirmed.
- Assert that a WDC-sourced domain (`discovery_source = 'wdc'`) skips the Web Risk step entirely, even with a valid API key configured.
- Assert that when `GOOGLE_WEB_RISK_KEY` is not set, the check is skipped and returns `true` without throwing.
