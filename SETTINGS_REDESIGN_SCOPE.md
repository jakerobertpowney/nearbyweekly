# NearbyWeekly — Settings Page Redesign + Avatar Dropdown Link

## Overview

Two changes required:
1. Add a "Settings" link to the avatar dropdown in `AppTopBar.vue`
2. Redesign the settings pages to match the brand (orange-500, slate, Poppins — same as onboarding and preferences)

---

## Part 1 — Avatar Dropdown (`AppTopBar.vue`)

The dropdown currently contains only "Sign out". Add a "Settings" link above it.

**Target route:** `/settings/profile` (the existing profile settings route)

**Updated dropdown markup:**

```html
<div
  v-if="dropdownOpen"
  class="absolute right-0 top-10 z-50 w-48 overflow-hidden rounded-xl border border-slate-100 bg-white shadow-lg"
>
  <!-- User email -->
  <div class="border-b border-slate-100 px-4 py-3">
    <p class="truncate text-xs text-slate-400">{{ page.props.auth?.user?.email }}</p>
  </div>

  <!-- Settings link -->
  <Link
    :href="editSettings()"
    class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm text-slate-700 transition hover:bg-slate-50"
    @click="closeDropdown"
  >
    <Settings class="h-4 w-4 text-slate-400" />
    Settings
  </Link>

  <!-- Divider -->
  <div class="border-t border-slate-100" />

  <!-- Sign out -->
  <button
    type="button"
    class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm text-slate-700 transition hover:bg-slate-50"
    @click="signOut"
  >
    <LogOut class="h-4 w-4 text-slate-400" />
    Sign out
  </button>
</div>
```

**Changes to `AppTopBar.vue` script:**
- Import `Settings` and `LogOut` from `lucide-vue-next`
- Import the settings route: `import { edit as editSettings } from '@/routes/profile'`
- Widen dropdown from `w-40` to `w-48` to accommodate the longer items

**Active state on top bar:** The top bar tabs ("My Edits", "Preferences") should not highlight when on a `/settings/*` route. The existing `$page.url.startsWith(...)` checks already handle this correctly since settings routes start with `/settings/`.

---

## Part 2 — Settings Pages Redesign

### Current structure (to replace)

Three separate pages, each wrapped in `AppLayout` + `SettingsLayout` (a two-column layout with a left sidebar nav: Profile / Security / Appearance).

### New structure

**Collapse to a single `/settings/profile` page** containing all relevant sections in one scrollable view. This fits NearbyWeekly's minimal account model — there are only three real things a user can do: see their email, manage 2FA, and delete their account.

**Drop the Appearance page** (`settings/Appearance.vue`). NearbyWeekly has a fixed brand identity (white background, orange-500 accent). A dark mode toggle doesn't apply and adds unnecessary complexity. Remove the Appearance link from `SettingsLayout` and the route from the nav. The page file can remain but should not be linked.

**Remove `SettingsLayout`** (`layouts/settings/Layout.vue`) from use — the left-sidebar nav pattern is replaced by the section-based layout used on Preferences.

---

### New Settings Page Layout

Single page at `/settings/profile`, using `AppLayout` (top bar only), no sidebar.

```
┌────────────────────────────────────────────────────┐
│  AppTopBar                                         │
├────────────────────────────────────────────────────┤
│                                                    │
│  ACCOUNT                 ← orange eyebrow         │
│  Your account details    ← font-heading h1         │
│  Manage your email...    ← slate-500 description   │
│                                                    │
│  ── Email address ──────────────────────────────   │
│  your@email.com  (read-only pill)                  │
│  Magic link note                                   │
│                                                    │
│  ── Sign-in security ───────────────────────────   │
│  Explanation of magic links                        │
│  [2FA section if canManageTwoFactor]               │
│                                                    │
│  ── Danger zone ────────────────────────────────   │
│  Delete account button                             │
│                                                    │
└────────────────────────────────────────────────────┘
```

---

### Section 1 — Email Address

**Eyebrow:** `YOUR ACCOUNT`
**Title:** `Account details`
**Description:** `Your email address is used to send your weekly edit and sign-in links.`

The email field is **read-only** — NearbyWeekly uses magic links, so there's no meaningful reason to let users change their email from within the app (it would break auth). Display it as a styled read-only value rather than an editable input:

```html
<div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4">
  <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Email address</p>
  <p class="mt-1 text-sm font-medium text-slate-900">{{ user.email }}</p>
  <p class="mt-1 text-xs text-slate-400">
    To change your email, contact us — your email is tied to your sign-in link.
  </p>
</div>
```

Style: `rounded-2xl border border-slate-200 bg-slate-50` — the same info-panel style used in the onboarding location step.

Remove the name field entirely — NearbyWeekly doesn't use a display name anywhere in the product.

---

### Section 2 — Sign-in Security

**Eyebrow:** `SECURITY`
**Title:** `Sign-in method`

Replace the generic `bg-muted/30 border-border/70` panel with the branded slate info-panel:

```html
<div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 space-y-2">
  <div class="flex items-center gap-2">
    <ShieldCheck class="h-4 w-4 text-orange-500" />
    <p class="text-sm font-semibold text-slate-900">Magic link authentication</p>
  </div>
  <p class="text-sm text-slate-500">
    Your account uses secure email sign-in links instead of passwords.
    Each link expires after one use and cannot be reused.
  </p>
</div>
```

**2FA sub-section (if `canManageTwoFactor`):**

Keep the existing enable/disable 2FA logic but restyle:
- "Enable 2FA" button: `rounded-xl bg-orange-500 text-white hover:bg-orange-600` (primary orange, not the default shadcn button)
- "Disable 2FA" button: `rounded-xl border border-red-200 bg-red-50 text-red-700 hover:bg-red-100` (destructive but not alarming)
- Recovery codes and setup modal: keep as-is, they're functional components that don't need restyling

---

### Section 3 — Danger Zone

**Eyebrow:** `DANGER ZONE`

The delete account section (`DeleteUser` component) should sit in its own visually separated section at the bottom. Give it a subtle red-tinted container to signal permanence:

```html
<div class="rounded-2xl border border-red-100 bg-red-50/50 px-5 py-5 space-y-3">
  <p class="text-sm font-semibold uppercase tracking-widest text-red-400">Delete account</p>
  <p class="text-sm text-slate-600">
    Permanently deletes your account, preferences, and all newsletter history.
    This cannot be undone.
  </p>
  <!-- DeleteUser component button trigger goes here -->
</div>
```

The `DeleteUser` component itself (the confirmation modal/form) stays unchanged.

---

### Section separators

Between each section, use a visual divider with an eyebrow label rather than a horizontal rule. The pattern from the onboarding step header works well here:

```html
<div class="pt-10 space-y-8">
  <div class="space-y-2">
    <p class="text-sm font-semibold uppercase tracking-widest text-orange-500">Security</p>
    <h2 class="font-heading text-xl font-bold text-slate-900">Sign-in method</h2>
  </div>
  <!-- section content -->
</div>
```

Each section is `pt-10` below the previous one. No `<hr>` or `<Separator>` components needed.

---

### Typography and layout tokens

Match preferences and onboarding exactly:

| Element | Class |
|---|---|
| Page container | `mx-auto w-full max-w-3xl px-4 py-10` |
| Page eyebrow | `text-sm font-semibold uppercase tracking-widest text-orange-500` |
| Page title | `font-heading text-3xl font-bold text-slate-900` |
| Page description | `text-base leading-relaxed text-slate-500 max-w-2xl` |
| Section title | `font-heading text-xl font-bold text-slate-900` |
| Info panel | `rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4` |
| Primary button | `rounded-xl bg-orange-500 text-white hover:bg-orange-600` |

---

## Files to Change

| File | Change |
|---|---|
| `resources/js/components/AppTopBar.vue` | Add Settings link + email display to dropdown; add `Settings` + `LogOut` icon imports |
| `resources/js/pages/settings/Profile.vue` | Full rebuild — remove `SettingsLayout`, remove name field, read-only email, new brand styling |
| `resources/js/pages/settings/Security.vue` | Remove `SettingsLayout`, restyle info panels and 2FA buttons to brand |
| `resources/js/pages/settings/Appearance.vue` | No link to this page — leave the file but remove it from the settings nav and dropdown |
| `resources/js/layouts/settings/Layout.vue` | No longer used directly — can be left in place but is not imported by any settings page |

---

## What Stays the Same

- All settings routes (`/settings/profile`, `/settings/security`) — no backend changes
- `DeleteUser` component — functional, just placed inside the new danger zone container
- `TwoFactorSetupModal` and `TwoFactorRecoveryCodes` — functional components, no restyling needed
- The `Form` component usage in Profile — keep the same form bindings, just restyle the surrounding layout
