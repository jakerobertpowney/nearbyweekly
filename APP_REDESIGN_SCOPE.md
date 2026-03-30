# NearbyWeekly — Authenticated App Redesign Scope

## Objective

Replace the current generic Laravel sidebar starter-kit layout with a Mailbrew-style three-zone interface: a fixed branded top bar, a left panel listing past newsletter issues, and a main content area that renders the selected issue. The redesign must use the same brand tokens as the onboarding flow (orange-500, slate palette, Poppins) and eliminate all generic starter-kit artefacts (Repository/Documentation footer links, breadcrumb header, generic icons).

---

## Reference: Mailbrew Layout Pattern

The Mailbrew screenshot shows a three-zone layout:

```
┌─────────────────────────────────────────────────────────────┐
│  logo    [My Edits]  [Preferences]              [avatar]    │  ← fixed top bar
├───────────────────┬─────────────────────────────────────────┤
│                   │                                         │
│  Week of 27 Mar   │  27                                     │
│  16h ago          │  Friday, March 2026                     │
│  8 picks          │                                         │
│                   │  🎵 THIS WEEKEND ──────────────────     │
│  Week of 20 Mar   │  [event card]  [event card]             │
│  7 days ago       │                                         │
│  6 picks          │  📅 THIS WEEK ──────────────────────    │
│                   │  [event card]  [event card]             │
│  Week of 13 Mar   │                                         │
│  14 days ago      │  🗓 THIS MONTH ─────────────────────    │
│  5 picks          │  [event card]                           │
│                   │                                         │
└───────────────────┴─────────────────────────────────────────┘
```

The left panel is a scrollable issue list. Clicking an issue loads it in the main content area without a full page navigation — it behaves like a single-page inbox.

---

## Brand Tokens (Carry Over from Onboarding)

| Token | Value | Usage |
|---|---|---|
| `orange-500` | `#f97316` | Primary accent: active states, badges, CTA |
| `orange-100` | `#ffedd5` | Hover backgrounds, light fills |
| `slate-950` | `#020617` | Primary text, logo |
| `slate-700` | `#334155` | Secondary text |
| `slate-400` | `#94a3b8` | Muted / timestamps |
| `slate-100` | `#f1f5f9` | Borders, dividers |
| `white` | `#ffffff` | Panel and card backgrounds |
| `font-heading` | Poppins 600 | Section headers, issue titles |
| `rounded-2xl` | 1rem border-radius | Cards and panels |

---

## Zone 1 — Fixed Top Bar (`AppTopBar.vue`)

**Replaces:** `AppSidebarHeader.vue` and the current sidebar header section.

**Layout:** Full-width fixed bar, `h-14`, white background, `border-b border-slate-100`.

**Left:** NearbyWeekly wordmark + logo icon (existing `AppLogo` component). Clicking navigates to dashboard.

**Centre (primary navigation tabs):**
- "My Edits" → `/dashboard` (the newsletter history view)
- "Preferences" → `/preferences`

Tab style: underline-style active indicator using `border-b-2 border-orange-500 text-slate-900`, inactive state `text-slate-500 hover:text-slate-700`. No filled backgrounds — clean and minimal like Mailbrew's top nav.

**Right:** User avatar / email initial in a circle (`bg-orange-100 text-orange-700`), opens a small dropdown with "Sign out" only. Remove all settings/profile/appearance links — the user-facing experience is intentionally minimal.

**Responsive:** On mobile (`< md`), the centre tabs collapse into a hamburger or bottom tab bar. The issue list slides in from the left as a drawer. Detail below under Responsive section.

---

## Zone 2 — Left Issue List Panel (`NewsletterIssueList.vue`)

**Width:** Fixed `w-64` on desktop, hidden on mobile (slides in as a drawer on demand).

**Background:** `bg-slate-50`, `border-r border-slate-100`, full viewport height.

**Header row inside panel:**
- "Your edits" label in `font-heading text-sm font-semibold text-slate-700`
- Issue count badge: `text-xs text-slate-400`

**Each issue row:**
```
┌──────────────────────────────┐
│  27 March 2026               │  ← date, font-medium text-sm text-slate-900
│  16 hours ago · 8 picks      │  ← muted meta line, text-xs text-slate-400
└──────────────────────────────┘
```

- Left border accent when selected: `border-l-2 border-orange-500 bg-white`
- Inactive: `hover:bg-white border-l-2 border-transparent`
- Padding: `px-4 py-3`
- Clicking an issue updates the main content area (reactive `selectedRunId` in the parent page — no full page load)

**Empty state (no issues yet):**
```
Inbox icon
"No edits sent yet"
"Your first weekly edit will appear here."
```

**`no_matches` runs:** Show in the list with a muted style and label "No matches this week" rather than an event count. Tapping them shows a simple state message in the main content area rather than event cards.

---

## Zone 3 — Main Content Area (`NewsletterIssueView.vue`)

This is the reading pane. It renders the selected newsletter run.

**Date header (mirrors Mailbrew exactly):**
```
27                     ← large number, font-heading text-5xl font-bold text-slate-900
Friday                 ← day name, text-xl font-medium text-orange-500
March, 2026            ← month + year, text-xl text-slate-500
```

Below the date: Issue navigation arrows `← Issue #6 →` (previous/next issue) in a small row, matching the Mailbrew pattern. Also a row of action links: "View in email" (links to the original email URL if stored) and "Preferences" (shortcut to preferences page).

**Time-bucketed sections (from `NEWSLETTER_MATCHING_SCOPE.md`):**

Each non-empty bucket renders as:
```
🎵 THIS WEEKEND  ─────────────────────────  (eyebrow label + rule)
[event card] [event card]
```

Eyebrow label: Poppins 600, 11px, uppercase, letter-spaced, `text-orange-500`. The rule is `border-t border-slate-100` stretching to the right.

**Event cards (updated from current Dashboard cards):**
- Keep the existing card structure (image strip, title, date, venue, CTA button)
- Two columns on desktop (`grid-cols-2`), one column on mobile
- Add "X miles away" distance line using `MapPin` icon (distance already on `NewsletterItem`)
- Add interest emoji badge (carry over from onboarding `getInterestEmoji()`) next to the category badge
- CTA button changes from "View event" to "Get tickets →" — links to `/events/{id}/go` (affiliate tracking route from CLAUDE.md task 8)

**Empty main state (no issue selected, or no runs yet):**
```
Large ticket emoji or Inbox icon
"Your weekly edit will appear here"
"Events are curated every Thursday morning."
```

---

## Pages and Routes Affected

### Dashboard (`/dashboard`) — `pages/Dashboard.vue`

The entire template needs to be rebuilt around the three-zone layout. The existing card/section structure is replaced by `NewsletterIssueList` + `NewsletterIssueView` components.

**Props passed from controller remain the same:** `latestRun`, `archiveRuns`, `preferencesComplete`. No backend changes needed for this redesign.

**New reactive state in the page:**
```ts
const selectedRunId = ref<number | null>(latestRun?.id ?? null)
const selectedRun = computed(() =>
  [latestRun, ...archiveRuns].find(r => r?.id === selectedRunId.value) ?? null
)
```

Clicking an issue in `NewsletterIssueList` sets `selectedRunId`. `NewsletterIssueView` receives `selectedRun` as a prop.

### Preferences (`/preferences`) — `pages/Preferences/Edit.vue`

- Wrap in the new `AppTopBar` layout (remove `AppLayout` / `AppSidebarLayout` wrapper)
- The page body becomes a single-column centred content area (`max-w-2xl mx-auto`)
- Remove the `breadcrumbs` prop pattern — navigation is now via the top bar tabs
- Visual style stays largely the same (the card-based form is already well-branded); minor changes: remove the "Magic-link account" badge header card, tighten to a single card with sections

### Layout — Remove Sidebar, Add Top Bar

**Delete or gut:** `AppSidebarLayout.vue` usage in authenticated pages.
**Keep but simplify:** `AppLayout.vue` — rewire it to use the new `AppTopBar.vue` + direct `<slot />` without the sidebar wrapper.
**Gut:** `AppSidebar.vue` — remove the Repository/Documentation footer links. The sidebar component itself can be repurposed as the issue list panel or retired entirely.
**Remove from `AppSidebar.vue`:** The `footerNavItems` array with Repository and Documentation links — these are Laravel starter-kit defaults that have no place in a user-facing product.

---

## New Components to Create

| Component | Purpose |
|---|---|
| `AppTopBar.vue` | Fixed top nav bar with logo, tabs, user avatar |
| `NewsletterIssueList.vue` | Left panel — scrollable list of past newsletter runs |
| `NewsletterIssueView.vue` | Main reading pane — renders selected run with buckets |
| `NewsletterBucketSection.vue` | Renders one time bucket (header + event grid) |
| `NewsletterEventCard.vue` | Single event card (extracted from current Dashboard inline card) |

---

## Responsive Behaviour

**Desktop (`≥ md`):** All three zones visible simultaneously. Left panel is `w-64` fixed.

**Mobile (`< md`):**
- Top bar stays, tabs collapse to two icon-only buttons + avatar
- Left panel hidden by default
- Main content fills the screen
- A "← All edits" button at the top of the reading pane slides in the left panel as a full-screen overlay (like a drawer)
- When no issue is selected (first visit on mobile), the issue list is shown full-screen with tap-to-open behaviour

---

## What Stays the Same

- **All backend controllers and routes** — no changes
- **Inertia page props** — the same data shape that the controller sends today
- **Preferences form logic** — same form fields, same `useForm` pattern
- **Auth flow** — magic link, onboarding, waiting page are outside scope
- **Onboarding pages** — already well-branded, left untouched
- **Brand tokens** — same values as `Onboarding/Start.vue`

---

## Implementation Order

1. Create `AppTopBar.vue` — establishes the new nav pattern and can be dropped in immediately
2. Gut `AppSidebar.vue` of starter-kit footer links; update `AppSidebar.vue` nav items to match product (Dashboard only)
3. Create `NewsletterEventCard.vue` — extract from current `Dashboard.vue` inline card markup
4. Create `NewsletterBucketSection.vue` — wraps bucket header + event card grid
5. Create `NewsletterIssueView.vue` — composes date header + bucket sections + empty states
6. Create `NewsletterIssueList.vue` — issue list panel with selection state
7. Rebuild `Dashboard.vue` — wire together all new components in three-zone layout
8. Update `Preferences/Edit.vue` — rewrap in new layout, remove breadcrumb pattern
9. Update `AppLayout.vue` — remove sidebar wrapper, use `AppTopBar` directly
10. Responsive pass — add mobile drawer behaviour to issue list

---

## Design Details — Issue List Date Display

Use `Intl.RelativeTimeFormat` for the relative timestamp ("16 hours ago", "7 days ago") matching Mailbrew's style. Show relative time if within 14 days, absolute short date (`27 Mar`) beyond that. The full date (`27 March 2026`) appears only in the reading pane header.

---

## Design Details — Reading Pane Date Header

Mailbrew's large-number date header works because it grounds the user in time. For NearbyWeekly it reads:

```
27          ← text-6xl font-bold font-heading text-slate-900
Friday      ← text-xl font-semibold text-orange-500    (matches brand)
March, 2026 ← text-xl text-slate-400
```

Below: a small metadata row in `text-xs text-slate-400`: "Issue #6 · 8 events · Sent 16 hours ago"
Then issue nav: `← Previous` and `Next →` as ghost buttons.
