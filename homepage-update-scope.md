# Homepage Update Scope — Replace Social Proof with Borrowed Credibility + Founder Story

## Summary

The current homepage has a **Social Proof** section (lines ~1193–1325 of `Welcome.vue`) containing fabricated testimonials ("Sarah, Bristol" and "Marcus, Edinburgh") and invented stats (2,400+ weekly readers, 67% open rate). These need to go before launch — they're dishonest, and anyone paying attention will notice they don't add up for a brand new product.

This scope replaces that section with two things that are both honest and more compelling at this stage: **borrowed credibility** (the data sources and scale behind the product) and a **founder story** told from the perspective of someone who was fed up with the way event discovery works.

---

## What's Being Removed

The entire `<!-- ───── SOCIAL PROOF ───── -->` section, which currently contains:

- A circular photo collage strip (decorative, reuses event category images)
- Heading: "People seem to like it" / "Real people. Real weekends."
- Two fake blockquote testimonials with made-up names and cities
- Three stat cards: "2,400+ weekly readers", "67% open rate", "4.2mi avg event distance"

All of this gets deleted.

---

## What's Replacing It

Two new sections go into the same slot (between the Category Showcase and the FAQ):

### Section 1: Borrowed Credibility — "What powers your picks"

A clean, factual section that shows visitors the product is built on real infrastructure — not just a person manually googling events.

**Layout:** Centred heading + a row of 3 cards (stacking on mobile).

**Heading:**
- Eyebrow: `HOW IT WORKS BEHIND THE SCENES`
- Title: `Thousands of events. One email worth opening.`

**Cards:**

| Icon/visual | Headline | Supporting text |
|---|---|---|
| Data/feed icon | Real event feeds | We pull from Ticketmaster, Data Thistle, and UK-wide event APIs — thousands of events refreshed every week. |
| Location pin icon | Matched to your postcode | Events are scored by distance, category fit, and timing. You only see what's actually near you and actually on soon. |
| Filter/sparkle icon | No spam. No filler. | One email, every Thursday. Up to 8 events, ranked by how well they match. No ads, no promoted listings, no noise. |

**Why this works:** It borrows credibility from Ticketmaster (a name people trust) and signals the scale of the data pipeline without claiming user numbers. The "no ads, no promoted listings" line addresses a scepticism most newsletter readers carry by default.

**Design notes:**
- Same visual style as the rest of the page — warm palette (`#c4623a` accent, `#fdf7f4` background)
- Cards should use the existing rounded-2xl, shadow-sm, white background pattern already used in the stats cards and FAQ
- Use Lucide icons (`Database`, `MapPin`, `Sparkles` or similar) to keep it consistent with the rest of the page which already imports from `lucide-vue-next`

---

### Section 2: Founder Story — "Why this exists"

A short, personal narrative written from the perspective of a real person who was frustrated by how broken event discovery is. This replaces the testimonials with something more honest and more relatable — because the founder *is* the first user.

**Layout:** Full-width warm background (`#fdf7f4`), centred single column, max-width ~640px. No cards, no grid — just text with some typographic presence.

**Heading:**
- Eyebrow: `WHY THIS EXISTS`
- Title: `I kept missing things happening on my doorstep.`

**Story copy (first person, conversational):**

> I'd hear about a food market the Monday after it happened. A friend would mention a comedy night I would've loved — "Oh, it was last Thursday." I tried Eventbrite, Ticketmaster, Instagram, local Facebook groups. Every week I'd spend 20 minutes scrolling and still end up on the sofa feeling like I'd missed out.
>
> The events were out there. I just couldn't find them without it feeling like a second job.
>
> So I built nearbyweekly. It pulls from the same feeds the big ticket sites use — Ticketmaster, Data Thistle, local listings — and matches them to your interests and your postcode. Then it sends you one email, every Thursday morning, with the stuff that's actually worth leaving the house for.
>
> No app to check. No algorithm to fight. Just a short email with things happening near you that you'd genuinely care about.
>
> That's it. I built the thing I wished existed.

**Attribution line:** `— Jake, founder` (styled like the old blockquote footers, using `#9c6b54`)

**Design notes:**
- The story should feel like a letter, not a marketing block. Generous line-height (1.75), slightly larger font size than body copy (~16–17px), warm text colour (`#6b4535`)
- No cards, no icons, no flourishes. The simplicity signals authenticity — it's a person talking, not a brand performing
- The paragraph breaks are intentional and should be preserved — each one marks a shift in the story (problem → failed solutions → what I built → how it works → punchline)
- If you want a subtle visual anchor, a single `◆` mark in the accent colour above the eyebrow works well, matching the logo/brand mark used in the nav

---

## Changes to `Welcome.vue`

### Template changes

1. **Delete** the entire `<!-- ───── SOCIAL PROOF ───── -->` section (approximately lines 1193–1325)
2. **Insert** the two new sections in the same position (between Category Showcase `</section>` and FAQ `<section>`)
3. **No changes** to hero, newsletter preview, category showcase, FAQ, final CTA, or footer

### Script changes

1. **Remove** any data or computed properties only used by the social proof section (there don't appear to be any — the section is purely inline)
2. **Add imports** for any new Lucide icons used in the credibility cards (e.g. `Database`, `Sparkles` — `MapPin` is already imported)
3. **No new reactive state needed** — both new sections are static content

### Style changes

1. **Remove** any scoped CSS rules that only applied to the social proof section (if any exist — the section appears to use inline styles and utility classes)
2. **Add** any new scoped styles needed for the founder story typography (line-height, font-size overrides)

---

## What This Scope Does NOT Cover

- **Adding real testimonials later.** Once real subscribers exist and provide feedback, a testimonials section can return. That's a future task — not this one.
- **Analytics or A/B testing.** No tracking changes. The click tracking route (`/events/{event}/go`) is a separate backlog item.
- **Copywriting for other sections.** The FAQ copy, hero copy, and CTA copy are unchanged.
- **New images or assets.** The borrowed credibility cards use Lucide icons, not photos. No new image files needed.

---

## Acceptance Criteria

- The fake testimonials and fabricated stats ("2,400+ weekly readers", "67% open rate") are completely gone from the codebase
- The borrowed credibility section renders 3 cards with factual claims about the data sources, matching logic, and email format
- The founder story reads naturally, is written in first person, and includes Jake's name
- Both sections match the existing page's visual style (colours, border radius, spacing, font families)
- The page still looks good on mobile — credibility cards stack vertically, story section flows naturally at narrow widths
- No broken imports, no console errors, no layout shifts from the removal of the old section
