# NearbyWeekly — Coral/Terracotta Colour Rebrand Scope

> **Goal:** Replace the existing orange (`#f97316`) colour scheme with the warm coral/terracotta palette throughout the app — CSS variables, Tailwind utility classes, inline hex values, and the email template.

---

## The New Palette

| Token name | Hex | Usage |
|---|---|---|
| `--brand-primary` | `#C4623A` | Main brand colour — buttons, active borders, focus rings |
| `--brand-primary-hover` | `#A84E2C` | Hover/pressed state for primary buttons |
| `--brand-soft` | `#E8956D` | Soft coral — badges, category pills, highlights |
| `--brand-accent` | `#D4A853` | Warm gold — featured labels, star ratings, special callouts |
| `--brand-surface` | `#FDF7F4` | Warm off-white — alternating sections, card surfaces, FAQ bg |
| `--brand-surface-deep` | `#F5EAE3` | Deeper warm cream — testimonial cards, section dividers |
| `--brand-border` | `#E8D5C8` | Warm beige — subtle borders and dividers |
| `--brand-text-primary` | `#1C1109` | Near-black with warm brown undertone — headlines, body |
| `--brand-text-muted` | `#6B4535` | Mid terracotta-brown — dates, distances, venue names |
| `--brand-text-subtle` | `#9C6B54` | Lighter warm brown — placeholders, secondary labels |

> **Background:** remains `#ffffff` flat white throughout. No warm tint on the main page background.

---

## What Needs Changing

There are four distinct layers to update.

---

### Layer 1 — CSS Custom Properties (`resources/css/app.css`)

The app uses Tailwind v4 with an `@theme inline` block. The CSS variables here drive shadcn/ui components (buttons, inputs, rings, etc.) sitewide.

**Current → New:**

```css
/* CURRENT */
--primary: hsl(0 0% 9%);
--primary-foreground: hsl(0 0% 98%);
--ring: hsl(0 0% 3.9%);

/* NEW — primary becomes the terracotta brand colour */
--primary: hsl(16 56% 50%);          /* #C4623A */
--primary-foreground: hsl(0 0% 98%); /* white — unchanged */
--ring: hsl(16 56% 50%);             /* #C4623A — focus rings match brand */
```

Add the full brand token set to the `:root` block:

```css
/* Brand palette tokens */
--brand-primary:      hsl(16 56% 50%);   /* #C4623A */
--brand-primary-hover: hsl(16 56% 41%);  /* #A84E2C */
--brand-soft:         hsl(20 67% 67%);   /* #E8956D */
--brand-accent:       hsl(38 59% 58%);   /* #D4A853 */
--brand-surface:      hsl(20 60% 97%);   /* #FDF7F4 */
--brand-surface-deep: hsl(20 47% 93%);   /* #F5EAE3 */
--brand-border:       hsl(20 37% 85%);   /* #E8D5C8 */
--brand-text-primary: hsl(22 74% 7%);    /* #1C1109 */
--brand-text-muted:   hsl(16 34% 31%);   /* #6B4535 */
--brand-text-subtle:  hsl(16 23% 47%);   /* #9C6B54 */
```

Also update chart colours to stay in the warm palette:

```css
/* CURRENT */
--chart-1: hsl(12 76% 61%);   /* orange — already close, but generic */
--chart-5: hsl(27 87% 67%);   /* orange */

/* NEW */
--chart-1: hsl(16 56% 50%);   /* terracotta primary */
--chart-2: hsl(173 58% 39%);  /* teal — keep as complementary */
--chart-3: hsl(197 37% 24%);  /* dark blue — keep */
--chart-4: hsl(38 59% 58%);   /* warm gold accent */
--chart-5: hsl(20 67% 67%);   /* soft coral */
```

---

### Layer 2 — Tailwind Utility Classes in Vue Components

The audit found `orange-*` and `amber-*` Tailwind classes scattered across these files. Each needs a like-for-like swap.

**Colour mapping:**

| Old class | New class | Notes |
|---|---|---|
| `bg-orange-50` | `bg-[#FDF7F4]` | Brand surface (warm off-white) |
| `bg-orange-100` | `bg-[#F5EAE3]` | Brand surface deep |
| `bg-orange-200` | `bg-[#E8D5C8]` | Brand border tone as bg |
| `bg-orange-500` | `bg-[#C4623A]` | Primary terracotta |
| `bg-orange-600` | `bg-[#A84E2C]` | Primary hover |
| `text-orange-500` | `text-[#C4623A]` | Primary terracotta text |
| `text-orange-600` | `text-[#A84E2C]` | Hover-level terracotta text |
| `text-orange-700` | `text-[#6B4535]` | Muted terracotta text |
| `text-orange-800` | `text-[#1C1109]` | Near-black warm text |
| `border-orange-200` | `border-[#E8D5C8]` | Warm beige border |
| `border-orange-300` | `border-[#E8956D]` | Soft coral border |
| `border-orange-400` | `border-[#C4623A]` | Primary border |
| `border-orange-500` | `border-[#C4623A]` | Primary border |
| `hover:border-orange-200` | `hover:border-[#E8D5C8]` | |
| `hover:bg-orange-200` | `hover:bg-[#F5EAE3]` | |
| `hover:bg-orange-600` | `hover:bg-[#A84E2C]` | |
| `to-amber-100` | `to-[#FDF7F4]` | Gradient endpoint (hero card) |

> **Tip for Claude Code:** Rather than searching manually, run:
> ```bash
> grep -rn "orange-\|amber-" resources/js/ --include="*.vue"
> ```
> to get a full list of locations, then apply substitutions file by file.

**Files affected (from audit):**

| File | Classes to change |
|---|---|
| `resources/js/components/AppLogo.vue` | `bg-orange-500` |
| `resources/js/components/AppTopBar.vue` | `text-orange-500`, `text-orange-700`, `border-orange-500`, `bg-orange-100`, `hover:bg-orange-200` |
| `resources/js/components/HeroCards.vue` | `to-amber-100`, `bg-orange-50` (plus hardcoded hex — see Layer 3) |
| `resources/js/components/InterestGroupPicker.vue` | `bg-orange-50`, `border-orange-300`, `border-orange-400`, `text-orange-600`, `text-orange-700`, `text-orange-800` |
| `resources/js/components/Newsletter/EventCard.vue` | `bg-orange-50`, `bg-orange-500`, `bg-orange-600`, `border-orange-200`, `hover:border-orange-200`, `hover:bg-orange-600`, `to-amber-100` |
| `resources/js/components/Newsletter/BucketSection.vue` | `text-orange-500` |
| `resources/js/components/Newsletter/IssueList.vue` | `border-orange-500` |
| `resources/js/components/Newsletter/IssueView.vue` | `text-orange-500` |

---

### Layer 3 — Hardcoded Hex Values in `HeroCards.vue`

`HeroCards.vue` has a `<style scoped>` block with green-tinted hex values left over from the earlier forest green phase. These need replacing with terracotta equivalents.

**File:** `resources/js/components/HeroCards.vue`

| Current value | New value | Used for |
|---|---|---|
| `#e8ede9` | `#E8D5C8` | Card border |
| `rgba(26, 53, 40, 0.12)` | `rgba(196, 98, 58, 0.12)` | Card drop shadow |
| `rgba(26, 53, 40, 0.18)` | `rgba(196, 98, 58, 0.18)` | Card drop shadow (hover) |
| `rgba(26, 53, 40, 0.08)` | `rgba(196, 98, 58, 0.08)` | Mobile card shadow |
| `#e8f0eb` | `#F5EAE3` | Category badge background |
| `#1a3528` | `#C4623A` | Category badge text |
| `#0f1a14` | `#1C1109` | Card title text |
| `#6b8c7a` | `#6B4535` | Muted card text (date, distance) |
| `#f2f7f4` | `#FDF7F4` | Distance chip background |

---

### Layer 4 — Email Template (`resources/views/emails/newsletters/weekly.blade.php`)

The email uses hardcoded hex values throughout its inline CSS. These must stay inline (email clients strip stylesheets). The primary brand colour `#f97316` appears 13 times and needs replacing with `#C4623A`.

**Full substitution list:**

| Current hex | New hex | Occurrences | Used for |
|---|---|---|---|
| `#f97316` | `#C4623A` | ~13 | Primary brand — buttons, borders, accent lines, badge text |
| `#fff7ed` | `#FDF7F4` | 1 | Light brand-tinted container background |
| `#ffedd5` | `#F5EAE3` | 1 | Badge/pill background |
| `#f1f5f9` | `#f8f4f1` | ~8 | Page background (swap cool slate for warm off-white) |
| `#0f172a` | `#1C1109` | 3 | Primary text (swap cool slate-dark for warm near-black) |
| `#94a3b8` | `#9C6B54` | 4 | Secondary text (swap cool slate for warm muted) |
| `#64748b` | `#6B4535` | 4 | Tertiary text (swap cool slate for warm muted) |

> `#ffffff`, `#e2e8f0`, `#f8fafc`, `#475569` — these neutrals can stay as-is or be reviewed separately. They are low-priority since they don't carry brand identity.

**Run this sed command to apply all email substitutions at once:**

```bash
sed -i \
  -e 's/#f97316/#C4623A/g' \
  -e 's/#fff7ed/#FDF7F4/g' \
  -e 's/#ffedd5/#F5EAE3/g' \
  -e 's/#f1f5f9/#f8f4f1/g' \
  -e 's/#0f172a/#1C1109/g' \
  -e 's/#94a3b8/#9C6B54/g' \
  -e 's/#64748b/#6B4535/g' \
  resources/views/emails/newsletters/weekly.blade.php
```

> After running, send a test newsletter with `php artisan newsletters:send-weekly` and check the output in Mailhog to verify the colour changes look correct.

---

## What Does NOT Change

- All `text-slate-*`, `bg-slate-*`, `border-slate-*` neutral utility classes — these are fine as neutral greys
- Red destructive colours (`text-red-600`, `bg-red-50`, etc.) — these are semantic, not brand
- `text-green-500` success indicator — semantic, leave alone
- Dark mode CSS variables — can be addressed in a follow-up pass once light mode is signed off
- `--sidebar-ring: hsl(217.2 91.2% 59.8%)` (blue) — sidebar-specific, low visibility, leave for now

---

## Verification Checklist

After applying all changes, check each of these visually:

- [ ] Primary CTA buttons (postcode input submit, onboarding "Continue") — terracotta fill, no orange
- [ ] Active nav/tab indicators in AppTopBar — terracotta underline
- [ ] Interest pills (selected state) in InterestGroupPicker — terracotta border and text
- [ ] Logo mark — terracotta background
- [ ] Newsletter event cards — "Get Tickets" button is terracotta
- [ ] Newsletter bucket section headers — terracotta accent
- [ ] Hero cards — terracotta shadows, badges, title text
- [ ] Email template (Mailhog) — all accent elements are terracotta, background is warm off-white
- [ ] Focus rings on inputs — terracotta glow, not black
- [ ] No remaining orange-* Tailwind classes: `grep -rn "orange-\|amber-" resources/js/`
- [ ] No remaining `#f97316` anywhere: `grep -rn "f97316" resources/`

---

## Suggested Implementation Order

1. **`app.css`** — Update CSS variables first. This will immediately fix all shadcn/ui components (buttons, inputs, focus rings) sitewide.
2. **`HeroCards.vue`** — Scoped hex values, straightforward find-and-replace.
3. **`weekly.blade.php`** — Run the sed command above, verify in Mailhog.
4. **Vue component Tailwind classes** — Work through the affected files listed in Layer 2, one file at a time.
5. **Visual QA pass** — Run through the verification checklist above.

*See also: [Landing Page Scope](nearbyweekly-landing-page-design-scope.md) · [Floating Hero Spec](nearbyweekly-floating-hero-spec.md) · [Newsletter Preview Spec](nearbyweekly-newsletter-preview-spec.md)*
