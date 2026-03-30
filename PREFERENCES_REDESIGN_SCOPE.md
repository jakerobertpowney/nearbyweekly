# NearbyWeekly — Preferences Page Redesign Scope

## Objective

Rebuild `resources/js/pages/Preferences/Edit.vue` so it uses the same visual language as `Onboarding/Start.vue` — full-bleed white background, no card wrappers, large Poppins headings, orange selection states, emoji interest grid, and a sticky save footer. The page is a single scrollable view (not a stepper), but every section should look and feel like it belongs to the same product as onboarding.

---

## What the Onboarding Flow Establishes (the design source of truth)

These patterns must be replicated exactly on the preferences page:

| Element | Onboarding class/style |
|---|---|
| Page background | `bg-white min-h-screen` |
| Content container | `mx-auto w-full max-w-3xl py-10` |
| Section spacing | `space-y-8` between header and content within each section |
| Section gap | `pt-12` or equivalent between sections |
| Eyebrow label | `text-sm font-semibold uppercase tracking-widest text-orange-500` |
| Section title | `font-heading text-3xl font-bold text-slate-900 sm:text-4xl` |
| Section description | `text-base leading-relaxed text-slate-500 max-w-2xl` |
| Interest card (unselected) | `rounded-2xl border-2 border-slate-200 bg-white hover:border-orange-200 hover:bg-orange-50/40` |
| Interest card (selected) | `rounded-2xl border-2 border-orange-400 bg-orange-50 shadow-sm` |
| Interest card checkmark | `absolute right-3 top-3 h-5 w-5 rounded-full bg-orange-500` with `CheckCircle2` icon |
| Radius card (unselected) | `rounded-2xl border-2 border-slate-200 bg-white hover:border-orange-200 hover:bg-orange-50/40` |
| Radius card (selected) | `rounded-2xl border-2 border-orange-400 bg-orange-50 shadow-sm` |
| Radius circle (unselected) | `h-12 w-12 rounded-full bg-slate-100 text-slate-600 font-bold` |
| Radius circle (selected) | `h-12 w-12 rounded-full bg-orange-500 text-white font-bold` |
| Postcode input | `h-14 rounded-2xl border-slate-300 pl-12 text-lg font-semibold uppercase tracking-wide focus-visible:ring-orange-300` |
| Postcode icon | `MapPin` at `absolute left-4 top-1/2 -translate-y-1/2 text-slate-400` |
| Primary button | `rounded-xl bg-orange-500 px-6 text-white hover:bg-orange-600` |

---

## Page Structure

The preferences page renders all sections on one scrollable page. No steps, no wizard. Wrap in `AppLayout` (the new top-bar layout — already in place).

```
┌────────────────────────────────────────────────────┐
│  AppTopBar                                         │
├────────────────────────────────────────────────────┤
│                                                    │
│  [Success alert if status is set]                  │
│                                                    │
│  YOUR INTERESTS          ← orange eyebrow         │
│  What kind of events do you love?  ← h1           │
│  Pick everything you'd genuinely...  ← p          │
│  [emoji interest grid]                             │
│                                                    │
│  YOUR LOCATION                                     │
│  What postcode should we search around?            │
│  [large postcode input with MapPin icon]           │
│  🇬🇧 We currently cover events across the UK       │
│                                                    │
│  HOW FAR WILL YOU TRAVEL?                          │
│  How far are you happy to travel?                  │
│  [radius option cards]                             │
│                                                    │
│  NEWSLETTER                                        │
│  Keep the weekly edit active                       │
│  [toggle card]                                     │
│                                                    │
│  ─────────────────────────────────────────────    │
│  [sticky bottom footer with Save button]           │
└────────────────────────────────────────────────────┘
```

---

## Section 1 — Interests

**Eyebrow:** `YOUR INTERESTS`
**Title:** `What kind of events do you love?`
**Description:** `Pick everything you'd genuinely open an email for. We'll curate the best of each category near you every week.`

**Grid:** `grid grid-cols-2 gap-3 sm:grid-cols-3` — identical to onboarding Step 1.

Each interest button is the full emoji card from onboarding:
- `relative flex flex-col items-start gap-3 rounded-2xl border-2 p-4 text-left transition-all duration-200`
- `text-2xl leading-none` emoji (use the same `getInterestEmoji()` function from onboarding — copy it into the preferences page script)
- `text-sm font-medium leading-snug` interest name
- `text-orange-900` when selected, `text-slate-800` when not
- Absolute `CheckCircle2` in orange circle at top-right when selected

Below the grid, show the selected interests pill row exactly as in onboarding:
```
Selected: [pill] [pill] [pill]
```
Pills: `rounded-full bg-orange-100 px-3 py-0.5 text-sm font-medium text-orange-700`

---

## Section 2 — Location

**Eyebrow:** `YOUR LOCATION`
**Title:** `What postcode should we search around?`
**Description:** `Use your home base or the area you most often want plans for.`

**Input:** Identical to onboarding Step 2 — large `h-14 rounded-2xl` input with `MapPin` icon at left, uppercase, tracking-wide.

Below the input, the UK coverage note:
```
rounded-2xl border border-slate-200 bg-slate-50 p-4
🇬🇧 We currently cover events across the UK
```

**Postcode validation:** Apply the same client-side regex already in onboarding:
```ts
const postcodePattern = /^[A-Z]{1,2}\d[A-Z\d]?\s?\d[A-Z]{2}$/i
```
Show an inline `InputError` beneath the input if the postcode doesn't match on blur.

---

## Section 3 — Travel Radius

**Eyebrow:** `HOW FAR WILL YOU TRAVEL?`
**Title:** `How far are you happy to travel?`
**Description:** `Be realistic — a tighter radius means sharper, more actionable recommendations.`

**Radius cards:** `grid gap-3 sm:grid-cols-2` — identical to onboarding Step 3.

Each card:
- `relative flex items-center gap-4 rounded-2xl border-2 p-5 text-left transition-all duration-200`
- Left circle showing the radius number — `bg-orange-500 text-white` when selected, `bg-slate-100 text-slate-600` otherwise
- `CheckCircle2` in orange circle at top-right when selected
- Description line using the same `radiusDescription()` helper from onboarding — copy it into the preferences page script

---

## Section 4 — Newsletter Toggle

**Eyebrow:** `NEWSLETTER`
**Title:** `Keep the weekly edit active`
**Description:** `Turn this off to pause sends without removing your preferences.`

Restyle the current checkbox card to match the section pattern. The toggle itself should be a single `rounded-2xl border-2` card (like an interest card) that the user clicks to toggle, with a visual on/off state:

- **Active:** `border-orange-400 bg-orange-50` with a `CheckCircle2` in orange circle top-right and text `Weekly edit is active`
- **Inactive:** `border-slate-200 bg-white` with muted text `Weekly edit is paused`

This replaces the current `Label + Checkbox` compound with a more visually consistent toggle card that matches the rest of the page.

---

## Sticky Save Footer

Replace the current `CardFooter`-based save button with a sticky footer identical in structure to the onboarding footer:

```html
<footer class="sticky bottom-0 border-t border-slate-100 bg-white/95 px-6 py-4 backdrop-blur">
  <div class="mx-auto flex max-w-3xl items-center justify-between">
    <span class="text-sm text-slate-400">
      Changes save immediately when you click Save
    </span>
    <Button
      class="rounded-xl bg-orange-500 px-6 text-white hover:bg-orange-600"
      :disabled="form.processing"
      @click="submit"
    >
      Save preferences
      <ArrowRight class="h-4 w-4" />
    </Button>
  </div>
</footer>
```

No separate "Back" button is needed on this page since there is no stepper.

---

## Success Alert

When `status` prop is set (after a successful save), render the alert at the very top of the content area — above all sections. Use the existing emerald alert style but remove it from inside a card. It should sit inline:

```html
<Alert class="mb-8 border-emerald-200 bg-emerald-50 text-emerald-800">
  <Sparkles class="h-4 w-4 text-emerald-700" />
  <AlertTitle>Preferences saved</AlertTitle>
  <AlertDescription>{{ status }}</AlertDescription>
</Alert>
```

---

## Layout Wrapper

The page already uses `AppLayout` (updated to use `AppTopBar`). The inner container should be:

```html
<div class="mx-auto w-full max-w-3xl px-4 py-10 pb-28">
  <!-- pb-28 leaves room for the sticky footer -->
```

`pb-28` ensures the sticky footer never overlaps the last section's content when scrolled to bottom.

---

## Helper Functions to Copy from Onboarding

Both of these exist in `Onboarding/Start.vue` and should be copied verbatim into `Preferences/Edit.vue`:

**`getInterestEmoji(slug: string): string`** — maps interest slug to emoji. Required for the interest grid.

**`radiusDescription(radius: number): string`** — returns the human description for each radius option. Required for the radius cards.

Consider extracting both into a shared composable `resources/js/composables/useNearbyWeeklyHelpers.ts` rather than duplicating them, then importing in both files. This is the tidier approach.

---

## What to Remove

- All `Card`, `CardHeader`, `CardContent`, `CardFooter` wrappers — replaced by flat sections
- The `Badge` import (the "Magic-link account" / "Passwordless by design" header badges are removed)
- The `breadcrumbs` prop and `BreadcrumbItem` type — navigation is now handled by `AppTopBar`
- The `edit` route import (`import { edit } from '@/routes/preferences'`) — no longer needed since there are no breadcrumbs
- The `Settings2` icon and the section icon pattern (`rounded-full bg-amber-100 p-2` icon container) — replaced by text eyebrows
- The `MapPin` import from the alert — replaced by the inline postcode input icon usage
- The location alert ("Location drives relevance") — not needed; the section description carries that message

---

## Files Changed

| File | Change |
|---|---|
| `resources/js/pages/Preferences/Edit.vue` | Full template rebuild — same script logic, new template structure |
| `resources/js/composables/useNearbyWeeklyHelpers.ts` | New file — extracts `getInterestEmoji()` and `radiusDescription()` for shared use (optional but recommended) |
