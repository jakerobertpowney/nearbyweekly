# NearbyWeekly — Newsletter Email Redesign Scope

## Objective

Redesign the weekly newsletter email template (`resources/views/emails/newsletters/weekly.blade.php`) to match the visual identity of the onboarding flow. A user who signs up through the onboarding and then receives their first newsletter should experience them as the same product.

---

## Brand Tokens (extracted from the app)

These are the exact values to carry into the email. All CSS must be inline — email clients strip `<style>` blocks and external sheets.

**Colours**

| Token | Hex | Usage |
|---|---|---|
| Orange 500 | `#f97316` | Primary CTA buttons, logo background, eyebrow labels, selected states |
| Orange 400 | `#fb923c` | Hover/border accents |
| Orange 100 | `#ffedd5` | Interest badge backgrounds, tinted containers |
| Orange 50 | `#fff7ed` | Card tint backgrounds |
| Amber 50 | `#fffbeb` | Gradient companion to orange-50 |
| Slate 900 | `#0f172a` | Primary headings |
| Slate 700 | `#334155` | Strong body text |
| Slate 600 | `#475569` | Body text |
| Slate 500 | `#64748b` | Secondary body text |
| Slate 400 | `#94a3b8` | Muted/helper text |
| Slate 200 | `#e2e8f0` | Borders, dividers |
| Slate 100 | `#f1f5f9` | Subtle section backgrounds |
| Slate 50 | `#f8fafc` | Page/card backgrounds |
| White | `#ffffff` | Base background |
| Emerald 100 | `#d1fae5` | Success state backgrounds |
| Emerald 500 | `#10b981` | Success icons |

**Typography**

The app uses Poppins (via Google Fonts) for headings and Instrument Sans for body text. In email:
- Attempt to load Poppins via `@import` at the top of the `<style>` block — Gmail on web supports it, Apple Mail supports it, Outlook does not
- Always provide a fallback stack: `'Poppins', 'Segoe UI', Arial, sans-serif` for headings, `Arial, Helvetica, sans-serif` for body
- Never rely on custom fonts rendering — the layout must look correct in all clients using system fonts alone

| Role | Font | Weight | Size |
|---|---|---|---|
| Brand wordmark | Poppins | 600 | 18px |
| Section eyebrow | Poppins / Arial | 600 | 11px uppercase tracked |
| Event title | Poppins / Arial | 700 | 18px |
| Body / description | Arial | 400 | 15px |
| Meta (date, distance) | Arial | 400 | 13px |
| Muted helper text | Arial | 400 | 13px |

**Shape**

The app uses `rounded-2xl` (16px) for cards and containers and `rounded-xl` (12px) for buttons. Use `border-radius: 16px` for cards and `border-radius: 12px` for buttons and badges throughout the email.

---

## Email Structure

Max width: **600px**, centred, white background. The email must render correctly at mobile widths (320px minimum).

---

### 1. Header

Matches the top bar from the onboarding flow exactly.

- White background, 1px bottom border in `#f1f5f9`
- Left-aligned logo: orange square (`#f97316`, 32×32px, border-radius 10px) containing a ✦ sparkle character in white (16px), followed by **nearbyweekly** in Poppins 600 18px `#0f172a`
- Right-aligned: week label in small muted text (`#94a3b8`, 13px) — e.g. "Week of 27 March"
- Padding: 20px 24px

### 2. Hero / Personalised Intro

- Full-width section, white background, 32px top padding, 24px bottom padding
- Eyebrow label: `YOUR WEEKLY EDIT` — 11px, Poppins 600, uppercase, tracked, `#f97316`
- Heading: `Here's what's on near {postcode} this week` — Poppins 700, 26px, `#0f172a`
- Subtext: `{N} events matched to your interests, curated just for you.` — Arial, 15px, `#64748b`
- A thin orange divider line (2px, `#f97316`, 40px wide) below the heading for visual rhythm

### 3. Event Cards

One card per matched event, stacked vertically. Up to 8 events. Each card:

**Card container:**
- White background
- Border: 1px solid `#e2e8f0`
- Border-radius: 16px
- Margin bottom: 12px
- Overflow hidden (so image clips to rounded corners)

**Event image (if available):**
- Full-width image, 200px tall, object-fit cover
- If no image: a solid tinted banner in orange-50 (`#fff7ed`) with the category emoji centred (32px), 120px tall

**Card body:** 20px padding

- **Category badge:** pill shape, `border-radius: 20px`, background `#ffedd5`, text `#f97316`, font-size 11px, Poppins 600, uppercase, tracked — e.g. `🎵 CONCERTS`. Displayed top-left above the title.
- **Event title:** Poppins 700, 18px, `#0f172a`, margin-top 8px
- **Venue + date row:** Two lines of meta in Arial 13px `#64748b`:
  - `📍 {venue_name}, {city}`
  - `📅 {formatted date}` — e.g. "Saturday 5 April, 7:30pm"
- **Distance chip:** Small inline chip — background `#f1f5f9`, border-radius 20px, padding 4px 10px, text `#475569` 12px — e.g. `{n} miles away`
- **CTA button:** Full-width, `border-radius: 12px`, background `#f97316`, text white, Poppins 600, 15px, padding 14px — text: `Get tickets →`. Links to `/events/{id}/go` (affiliate tracking route).

### 4. Interest Match Indicator (optional, nice-to-have)

Between the category badge and the event title, show which of the user's interests matched this event. A single small line: `Matched because you follow: {Interest Name}` in Arial 12px `#94a3b8`. Only show if the matched interest can be determined from `matched_interest_ids`.

### 5. Divider Between Sections

A clean horizontal rule — 1px `#f1f5f9`, no margin collapse — used between the hero and event list, and between the event list and footer.

### 6. Footer

- Background: `#f8fafc` (slate-50)
- Border-top: 1px `#e2e8f0`
- Padding: 32px 24px
- Centred layout, Arial 13px, `#94a3b8`

Content in this order:
1. **Logo repeat** — small: orange square (20px) + "nearbyweekly" in Poppins 600 14px `#475569`
2. **Tagline** — `Your weekly edit, curated by interest and location.` — 13px `#94a3b8`
3. **Preference links row** — two inline links:
   - `Update my preferences` → `/preferences`
   - `Unsubscribe` → `{$unsubscribeUrl}`
   - Styled as plain text links in `#f97316`, no underline
4. **Legal line** — `You're receiving this because you signed up at nearbyweekly.co.uk. We'll never share your email.` — 12px `#94a3b8`

---

## Email-Safe Implementation Rules

These are non-negotiable for broad client compatibility:

- **All CSS must be inline** — no `<style>` blocks in the `<body>`, no `<link>` tags. A single `<style>` block in `<head>` for the Poppins import and basic resets is acceptable, but every element must also carry inline styles as a fallback.
- **Table-based layout for the outer structure** — use `<table>` elements for the outer wrapper, header, and footer containers. Cards can use `<div>` with `display: block` since they're stacked vertically and don't require column layout.
- **No flexbox or grid in structural elements** — Outlook and some older mobile clients don't support them. Use `display: inline-block` or table cells for any side-by-side layout.
- **Images must have explicit `width` and `height` attributes** and include `alt` text for clients that block images by default.
- **All links must be absolute URLs** — never relative paths in email.
- **Use `mso-` conditional comments** for Outlook-specific fixes if needed (e.g. button padding).
- **Preview text** — add a hidden `<span>` immediately after `<body>` containing a preview string: `{N} events near {postcode} this week — {first event title} and more.` This appears in inbox previews before the email is opened.

---

## Blade Template Variables

The template receives these from `WeeklyNewsletterMail`:

```php
$user          // User model — use $user->postcode, $user->name
$matches       // Collection of {event: Event, score: float, distance_miles: float}
$unsubscribeUrl // Signed URL string
```

Helper usage within the template:

```blade
{{-- Formatted date --}}
{{ $match['event']->starts_at->format('l j F, g:ia') }}

{{-- Rounded distance --}}
{{ round($match['distance_miles']) }} miles away

{{-- Affiliate URL --}}
{{ url('/events/' . $match['event']->id . '/go') }}

{{-- Category emoji — map category slug to emoji in the template or a helper --}}
```

---

## Category → Emoji Mapping

Mirror the mapping from `Onboarding/Start.vue` so category badges feel consistent:

| interest slug | emoji |
|---|---|
| concerts | 🎵 |
| sports | ⚽ |
| comedy | 😂 |
| food-and-drink | 🍽️ |
| tech | 💻 |
| family-days-out | 👨‍👩‍👧 |
| markets | 🛍️ |
| wellness | 🧘 |
| hiking | 🌿 |
| theatre | 🎭 |
| festivals | 🎪 |
| farming-and-rural | 🌾 |
| arts-and-culture | 🎨 |

---

## Testing Checklist

Before marking the template complete, verify it renders correctly in:

- **Mailhog (local)** — trigger `php artisan newsletters:send-weekly` with a seeded user, open in Mailhog UI
- **Gmail (web)** — forward from Mailhog or use Mailtrap, open in Chrome
- **Apple Mail (macOS)** — check font rendering and image handling
- **Mobile (iPhone Mail app)** — check layout at narrow widths

The `/newsletter/preview` route already exists and renders `WeeklyNewsletterMail` directly in the browser — use this for fast iteration before sending.
