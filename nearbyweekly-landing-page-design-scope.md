# NearbyWeekly — Landing Page Design Scope

> **Goal:** A single-page marketing site that converts cold visitors into newsletter subscribers. The page should feel like an invitation to a great weekend — not a software product pitch.

---

## Brand DNA (Carry Forward)

| Element | Value |
|---|---|
| Primary (dark) | Deep forest green `#1a3528` |
| Primary (mid) | Forest green `#2d5a45` |
| Accent | Warm gold `#c9a84c` |
| Dark text | Near-black (green-tinted) `#0f1a14` |
| Muted text | Mid forest `#4a6355` |
| Background | White `#ffffff` — flat, no gradients |
| Card surface | `#f7faf8` (very subtle green-tinted white) |
| Heading font | **Poppins** (bold, punchy) |
| Body font | **Instrument Sans** (clean, readable) |
| Logo mark | Stylised location-pin or `◆` mark in forest green on white rounded square |
| Personality | Trustworthy, local, editorial — like a knowledgeable friend, not a ticket vendor |

---

## Core Messaging Hierarchy

Every section of the page reinforces **one idea**: you don't search for events — they find you.

```
Primary:    "Your weekend, sorted."
Secondary:  "A weekly email of events you'd actually leave the house for."
Supporting: "Tell us where you are and what you love. We do the rest."
```

The tone is conversational, warm, and slightly cheeky. Think "a mate who always knows what's on" — not a corporate events directory.

---

## Reference Site Takeaways

### From 1440 (join1440.com)
- **Newsletter-first strategy** — the website exists to convert, not to be a content hub
- **Issue preview** — lets visitors peek at what they'd receive, building trust
- **Minimal distractions** — one CTA repeated throughout, no sidebar, no nav maze
- **Social proof bar** — subscriber count front and centre
- **"No clickbait • 100% free • Unsubscribe anytime"** trust anchors near every CTA

### From Mailbrew (mailbrew.com)
- **Warm colour palette** — approachable, not corporate
- **Sample digest previews** — shows real output so visitors know exactly what they're signing up for
- **Source variety showcase** — demonstrates breadth of content sources
- **Clean card-based layout** — digestible sections, generous whitespace

### What We're Borrowing
- 1440's "preview the actual newsletter" conversion technique
- Mailbrew's warm, card-based visual language
- Both sites' obsession with a single, repeated CTA
- The trust-building pattern: show the product before asking for the email

### What We're Doing Differently
- **Ours is visual and local** — event cards with photos, distances, and venue names (not text-heavy news summaries)
- **Onboarding IS the funnel** — our multi-step flow (interests → postcode → radius → email) is a feature, not friction
- **Fun factor** — events are about having a good time, so the page should feel like one

---

## Page Structure — Section by Section

The page is a single vertical scroll. Each section has a clear job in the conversion funnel.

---

### Section 1 — Hero

**Job:** Stop the scroll. Make the value obvious in under 3 seconds.

```
┌─────────────────────────────────────────────────────────────┐
│  ✦ nearbyweekly                              [Build My Picks]  │
│─────────────────────────────────────────────────────────────│
│                                                             │
│           YOUR WEEKEND,                                     │
│              SORTED. 🎉                                     │
│                                                             │
│    A weekly email of events you'd                           │
│    actually leave the house for.                            │
│                                                             │
│    ┌─────────────────────────────────┐                      │
│    │  📍 Enter your postcode         │  [Show me what's on] │
│    └─────────────────────────────────┘                      │
│                                                             │
│    No searching. No scrolling. Just a                       │
│    weekly email with things worth doing.                    │
│                                                             │
│    ✓ 100% free  ✓ Unsubscribe anytime  ✓ No spam, ever    │
│                                                             │
│    "Trusted by 2,400+ people across the UK"                 │
│                                                             │
├─────────────────────────────────────────────────────────────│
│                                                             │
│    ┌──────────────────────────────────────────┐             │
│    │  ┌─────────┐  ┌─────────┐  ┌─────────┐  │             │
│    │  │ 📸      │  │ 📸      │  │ 📸      │  │             │
│    │  │ Comedy  │  │ Market  │  │ Gig     │  │             │
│    │  │ Night   │  │ Sunday  │  │ Night   │  │             │
│    │  │ 2.1 mi  │  │ 0.8 mi  │  │ 4.5 mi  │  │             │
│    │  └─────────┘  └─────────┘  └─────────┘  │             │
│    │        ↑ Mock newsletter preview         │             │
│    └──────────────────────────────────────────┘             │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Design Notes:**
- The hero splits into two zones: left copy + CTA, right newsletter mockup
- On mobile, copy stacks above the mockup
- The postcode input is the **primary CTA** — entering a postcode kicks off the onboarding flow directly (skipping a generic "sign up" step)
- The mockup on the right is a stylised version of the actual weekly email, showing 3 event cards with photos, category badges, and distance chips
- Background: white `#ffffff` — clean and flat

**Imagery:**
- 📸 Newsletter mockup — a slightly tilted, drop-shadowed card showing real event data
- Background texture: optional very light noise texture at 3% opacity

---

### Section 2 — How It Works

**Job:** Overcome the "what is this?" objection in 10 seconds flat.

```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│              HOW IT WORKS                                   │
│         (It's literally three things)                       │
│                                                             │
│    ┌───────────┐    ┌───────────┐    ┌───────────┐         │
│    │           │    │           │    │           │         │
│    │  📍       │    │  🎭       │    │  📬       │         │
│    │           │    │           │    │           │         │
│    │  DROP     │    │  PICK     │    │  OPEN     │         │
│    │  YOUR     │    │  YOUR     │    │  YOUR     │         │
│    │  POSTCODE │    │  PASSIONS │    │  INBOX    │         │
│    │           │    │           │    │           │         │
│    │  We find  │    │  Comedy?  │    │  Every    │         │
│    │  what's   │    │  Live     │    │  Thursday │         │
│    │  near you │    │  music?   │    │  morning, │         │
│    │           │    │  Food     │    │  your     │         │
│    │           │    │  markets? │    │  picks    │         │
│    │           │    │  You      │    │  land.    │         │
│    │           │    │  choose.  │    │           │         │
│    └───────────┘    └───────────┘    └───────────┘         │
│                                                             │
│              ──── • ──── • ────                             │
│       (dotted line connecting the three steps)              │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Design Notes:**
- Three cards or columns, connected by a dotted forest green line
- Each step has a large playful icon/illustration at the top (not a generic icon — something custom or from a consistent illustration set)
- Numbers hidden — the flow should feel effortless, not like instructions
- Optional: subtle animation where each card slides up as it enters the viewport

**Imagery:**
- Step 1 icon: Illustrated map pin dropping into a UK postcode
- Step 2 icon: A grid of colourful interest pills being tapped/selected
- Step 3 icon: An open envelope with event cards spilling out

---

### Section 3 — Newsletter Preview

**Job:** Show, don't tell. Let them see exactly what lands in their inbox.

```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│        "HERE'S WHAT LANDED LAST THURSDAY"                   │
│                                                             │
│   ┌─────────────────────────────────────────────────────┐   │
│   │  ✦ nearbyweekly        "What's on near SE1 this week" │   │
│   │─────────────────────────────────────────────────────│   │
│   │                                                     │   │
│   │  THIS WEEKEND                                       │   │
│   │  ┌────────────────┐  ┌────────────────┐             │   │
│   │  │ 📸             │  │ 📸             │             │   │
│   │  │ 🎭 Comedy      │  │ 🎵 Live Music  │             │   │
│   │  │                │  │                │             │   │
│   │  │ Monkey Barrel  │  │ Jazz Café      │             │   │
│   │  │ Comedy Club    │  │ Sessions       │             │   │
│   │  │ Sat 29 Mar     │  │ Fri 28 Mar     │             │   │
│   │  │ 📍 1.2 miles   │  │ 📍 3.4 miles   │             │   │
│   │  │                │  │                │             │   │
│   │  │ [Get Tickets]  │  │ [Get Tickets]  │             │   │
│   │  └────────────────┘  └────────────────┘             │   │
│   │                                                     │   │
│   │  THIS WEEK                                          │   │
│   │  ┌────────────────┐  ┌────────────────┐             │   │
│   │  │ 📸             │  │ 📸             │             │   │
│   │  │ 🍕 Food        │  │ 🏃 Fitness     │             │   │
│   │  │                │  │                │             │   │
│   │  │ Borough Market │  │ Park Run +     │             │   │
│   │  │ Night Market   │  │ Brunch Club    │             │   │
│   │  │ Wed 1 Apr      │  │ Sun 30 Mar     │             │   │
│   │  │ 📍 0.4 miles   │  │ 📍 2.1 miles   │             │   │
│   │  │                │  │                │             │   │
│   │  │ [Get Tickets]  │  │ [Get Tickets]  │             │   │
│   │  └────────────────┘  └────────────────┘             │   │
│   │                                                     │   │
│   └─────────────────────────────────────────────────────┘   │
│                                                             │
│            This could be YOUR inbox 👆                      │
│                                                             │
│               [Build My Weekly Picks]                       │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Design Notes:**
- This is the **highest-conversion section** — borrowing directly from 1440's "show the newsletter" technique
- Render a real (or realistic mock) of the weekly email inside a stylised "inbox" frame — slight drop shadow, email chrome at the top (sender name, subject line)
- Event cards inside should have actual photos, real venue names, real distances
- On mobile, the preview scrolls horizontally or stacks vertically
- The "This could be YOUR inbox" line + CTA sits directly below — strike while the iron is hot

**Imagery:**
- 📸 Use attractive stock photos of real UK events: a comedy club stage, a food market stall, a live music venue, a park run crowd
- The email frame itself should look like a real email client (subtle Gmail/Apple Mail chrome)

---

### Section 4 — Interest Showcase

**Job:** Let visitors feel the breadth of events covered. Create an "oh, they have THAT too?" moment.

```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│        WHATEVER YOU'RE INTO, WE'VE GOT IT                   │
│                                                             │
│   ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐     │
│   │ 🎵       │ │ 🍕       │ │ 🎭       │ │ 💪       │     │
│   │          │ │          │ │          │ │          │     │
│   │ MUSIC    │ │ FOOD &   │ │ ARTS &   │ │ HEALTH & │     │
│   │          │ │ DRINK    │ │ ENTER-   │ │ FITNESS  │     │
│   │ Live     │ │          │ │ TAINMENT │ │          │     │
│   │ gigs,    │ │ Markets, │ │          │ │ Yoga,    │     │
│   │ concerts │ │ tastings │ │ Theatre, │ │ runs,    │     │
│   │ festivals│ │ cooking  │ │ comedy,  │ │ cycling  │     │
│   │ indie,   │ │ classes, │ │ film,    │ │ martial  │     │
│   │ jazz,    │ │ wine,    │ │ spoken   │ │ arts,    │     │
│   │ folk...  │ │ craft    │ │ word...  │ │ wellness │     │
│   │          │ │ beer...  │ │          │ │          │     │
│   └──────────┘ └──────────┘ └──────────┘ └──────────┘     │
│                                                             │
│   ┌──────────┐ ┌──────────┐ ┌──────────┐                   │
│   │ 👨‍👩‍👧‍👦       │ │ 🌿       │ │ 💻       │                   │
│   │          │ │          │ │          │                   │
│   │ FAMILY   │ │ OUTDOORS │ │ TECH &   │                   │
│   │          │ │ & NATURE │ │ PROFES-  │                   │
│   │ Days     │ │          │ │ SIONAL   │                   │
│   │ out,     │ │ Hiking,  │ │          │                   │
│   │ kids     │ │ wildlife │ │ Meetups, │                   │
│   │ activs,  │ │ farming, │ │ conf-    │                   │
│   │ edu...   │ │ outdoor  │ │ erences, │                   │
│   │          │ │ advent-  │ │ work-    │                   │
│   │          │ │ ures...  │ │ shops... │                   │
│   └──────────┘ └──────────┘ └──────────┘                   │
│                                                             │
│            40+ interests. You pick. We match.               │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Design Notes:**
- Grid of category cards, each with a fun background image (blurred or colour-overlaid) and the category name + sub-interests listed
- Cards could have a subtle hover effect that reveals more sub-interests
- On mobile: horizontal scroll carousel
- Optional: make these cards clickable — tapping one could pre-select that interest and jump to the onboarding flow

**Imagery:**
- Each card gets a vibrant, high-energy background photo matching its category:
  - Music → crowd at a festival with hands up
  - Food & Drink → overhead shot of a bustling food market
  - Arts & Entertainment → theatre stage with dramatic lighting
  - Health & Fitness → group doing yoga in a park
  - Family → kids laughing at an outdoor event
  - Outdoors & Nature → hikers on a scenic UK trail
  - Tech & Professional → buzzy co-working event space

---

### Section 5 — Social Proof

**Job:** Build trust through numbers and real testimonials.

```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│        PEOPLE SEEM TO LIKE IT                               │
│                                                             │
│   ┌─────────────────────────────────────────────────────┐   │
│   │                                                     │   │
│   │  "I used to spend Sunday evening scrolling          │   │
│   │   Eventbrite for 30 minutes and still missing       │   │
│   │   things. Now I just open Thursday's email."        │   │
│   │                                                     │   │
│   │                         — Sarah, Bristol            │   │
│   │                                                     │   │
│   └─────────────────────────────────────────────────────┘   │
│                                                             │
│   ┌─────────┐    ┌─────────┐    ┌─────────┐                │
│   │  2,400+ │    │   67%   │    │  4.2mi  │                │
│   │         │    │         │    │         │                │
│   │ weekly  │    │  open   │    │ average │                │
│   │ readers │    │  rate   │    │ event   │                │
│   │         │    │         │    │ distance│                │
│   └─────────┘    └─────────┘    └─────────┘                │
│                                                             │
│   ┌─────────────────────────────────────────────────────┐   │
│   │                                                     │   │
│   │  "Finally a newsletter that doesn't feel like       │   │
│   │   spam. Every event is genuinely near me and        │   │
│   │   genuinely something I'd do."                      │   │
│   │                                                     │   │
│   │                         — Marcus, Edinburgh         │   │
│   │                                                     │   │
│   └─────────────────────────────────────────────────────┘   │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Design Notes:**
- Alternating pattern: testimonial quote → stats row → testimonial quote
- Testimonial cards have a subtle left-border in forest green `#2d5a45`
- Stats use large bold Poppins numbers with small descriptive labels beneath
- If real testimonials aren't available yet, use placeholder quotes that reflect the *kind* of feedback you'd want — replace with real ones as they come in
- Background: very light sage tint `#f7faf8` to differentiate from the white sections above and below

**Imagery:**
- Optional small avatar circles next to testimonials (can be illustrated/generic initially)
- Stats could have small decorative icons: a people icon, an envelope icon, a map pin

---

### Section 6 — Objection Handling / FAQ

**Job:** Squash the last doubts before the final CTA.

```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│        YOU MIGHT BE WONDERING...                            │
│                                                             │
│   ┌─────────────────────────────────────────────────────┐   │
│   │  ▸ Is it really free?                               │   │
│   │    Yes. Completely. No premium tier, no paywall.    │   │
│   │    We earn from affiliate ticket links — you pay    │   │
│   │    the same price either way.                       │   │
│   ├─────────────────────────────────────────────────────┤   │
│   │  ▸ How often will I get emails?                     │   │
│   │    Once a week, every Thursday morning. That's it.  │   │
│   │    No drip campaigns, no "you might also like"      │   │
│   │    spam. One email. The good stuff.                  │   │
│   ├─────────────────────────────────────────────────────┤   │
│   │  ▸ What if I move or my interests change?           │   │
│   │    Update your postcode, radius, or interests any   │   │
│   │    time from your preferences page. Your next       │   │
│   │    issue adapts instantly.                           │   │
│   ├─────────────────────────────────────────────────────┤   │
│   │  ▸ Can I unsubscribe easily?                        │   │
│   │    One click in every email. No guilt trip.         │   │
│   │    No "are you sure?" gauntlet. Just gone.          │   │
│   ├─────────────────────────────────────────────────────┤   │
│   │  ▸ Where do the events come from?                   │   │
│   │    We pull from Ticketmaster, Data Thistle, and     │   │
│   │    other UK event feeds. Thousands of events,       │   │
│   │    filtered down to the ones that matter to you.    │   │
│   └─────────────────────────────────────────────────────┘   │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Design Notes:**
- Accordion-style FAQ — questions visible, answers expand on click
- Keep answers short, punchy, and personality-forward
- The transparent revenue model ("affiliate links") builds trust — don't hide it

---

### Section 7 — Final CTA (The Closer)

**Job:** One last push. Make it feel inevitable.

```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│         ┌───────────────────────────────────────┐           │
│         │                                       │           │
│         │   STOP SEARCHING.                     │           │
│         │   START DISCOVERING.                  │           │
│         │                                       │           │
│         │   Your next favourite night out is     │           │
│         │   already happening near you.          │           │
│         │   Let us find it.                      │           │
│         │                                       │           │
│         │   ┌─────────────────┐                 │           │
│         │   │ 📍 Your postcode │ [Let's go →]   │           │
│         │   └─────────────────┘                 │           │
│         │                                       │           │
│         │   ✓ Free forever                      │           │
│         │   ✓ One email per week                │           │
│         │   ✓ Unsubscribe in one click          │           │
│         │                                       │           │
│         └───────────────────────────────────────┘           │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Design Notes:**
- Full-width section with a deep forest green background `#1a3528`
- The CTA card is centred, elevated with a strong drop shadow
- Copy shifts from informational to emotional — "stop searching, start discovering"
- Same postcode input + button pattern as the hero (consistency builds familiarity)
- Trust badges repeated beneath the input

---

### Section 8 — Footer

```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│  ✦ nearbyweekly                                               │
│                                                             │
│  Weekly UK event picks, tailored                            │
│  before you type your email.                                │
│                                                             │
│  Preferences · Privacy · Contact                            │
│                                                             │
│  Made with ☕ somewhere in the UK                            │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Design Notes:**
- Minimal footer — this is a landing page, not a portal
- Dark slate background with white/light text
- No social media links unless they exist and are active
- The tagline reinforces the brand positioning one last time

---

## Imagery & Visual Direction

### Photography Style
- **Authentic UK events** — not polished American stock photos. Think: a real comedy club with warm lighting, an actual borough market stall, muddy wellies at a countryside fair
- **Warm tones** — favour warm indoor glow and natural light — images should feel lived-in and local
- **People having fun** — laughter, movement, togetherness. No one posing stiffly for camera
- **Diversity** — reflect the real mix of people at UK events

### Illustration Style
- For icons and decorative elements, use a **hand-drawn or semi-flat illustration style** — friendly, slightly imperfect, warm
- Consistent line weight and colour palette (forest green, gold, off-white)
- Consider a custom illustration set for the "How It Works" steps

### Suggested Image Sources
- **Unsplash** — search: "UK food market", "comedy club audience", "festival crowd UK", "park run", "theatre audience"
- **Pexels** — good for lifestyle/event photography
- **Custom photography** — if budget allows, commission 5–10 photos at real UK events (highest-impact investment for authenticity)
- **Illustrations** — consider services like Blush.design, Stubborn.fun, or a freelance illustrator for a cohesive icon set

---

## Conversion Strategy

### Primary CTA: Postcode Input
The postcode field IS the signup flow. Entering a postcode doesn't just capture data — it starts the multi-step onboarding (interests → radius → email). This is the key differentiator from a generic "enter your email" box.

**Why this works:**
- **Micro-commitment:** typing a postcode feels low-stakes and curiosity-driven
- **Immediate personalisation:** the user is already investing in a tailored experience
- **Interest-first, email-last:** by the time they reach the email step, they've already chosen interests and radius — they're psychologically committed

### CTA Placement
The postcode input appears **three times** on the page:

1. **Hero** — above the fold, first thing visitors see
2. **Below newsletter preview** — after they've seen what they'll get
3. **Final closer** — emotional last push at the bottom

### Trust Signals (Repeated Throughout)
- "100% free"
- "Unsubscribe anytime"
- "No spam, ever"
- "One email per week"
- Subscriber count (once it's meaningful)
- Open rate stat (signals quality content)

### Mobile Optimisation
- Hero stacks vertically: copy → postcode input → newsletter mockup
- "How It Works" becomes a vertical stack or horizontal scroll
- Newsletter preview becomes scrollable
- Interest grid becomes a horizontal carousel
- Sticky bottom CTA bar appears after scrolling past the hero

---

## Page Performance Requirements

| Metric | Target |
|---|---|
| First Contentful Paint | < 1.5s |
| Largest Contentful Paint | < 2.5s |
| Total page weight | < 800KB (with images lazy-loaded) |
| Mobile Lighthouse score | > 90 |
| Images | WebP format, lazy-loaded below fold |
| Fonts | Subset Poppins (bold only) + Instrument Sans (400, 500) |

---

## Technical Notes

### Build Approach
This can be built as either:
- **A new Blade view** within the existing Laravel app (e.g. `resources/views/landing.blade.php`) — simplest, shares existing asset pipeline
- **A standalone static page** (HTML/CSS/JS) deployed separately — useful if you want to A/B test or use a different domain

**Recommendation:** Build within Laravel. The postcode input can POST directly to the existing onboarding flow, and you get Inertia routing for free.

### Key Interactions
- **Postcode input** → validates format client-side (UK regex) → redirects to `/onboarding` with postcode pre-filled
- **FAQ accordion** → pure CSS or minimal JS toggle
- **Scroll animations** → subtle fade-up on section entry (Intersection Observer, no heavy libraries)
- **Newsletter preview** → static HTML/CSS mockup (not a live embed)

### Analytics to Instrument
- Postcode input focus (intent signal)
- Postcode input submission (conversion event)
- Scroll depth (which sections are people seeing?)
- FAQ question clicks (what are people uncertain about?)
- Time on page
- Bounce rate by traffic source

---

## Suggested Build Order

1. **Hero + Final CTA** — get the conversion mechanism working first
2. **Newsletter Preview** — highest-impact social proof section
3. **How It Works** — simple, quick to build
4. **Interest Showcase** — adds depth and "oh, that too" factor
5. **Social Proof** — add when real testimonials/stats are available
6. **FAQ** — objection handling, refine based on real questions
7. **Polish** — animations, image optimisation, mobile fine-tuning

---

## Mood Board Keywords

Search for visual inspiration using these terms:

> warm · local · weekend vibes · golden hour · UK high street · festival glow · comedy night · street food steam · park run sunrise · craft beer taps · theatre marquee · farmer's market bunting · friends laughing · hand-stamped wristband · fairy lights · chalkboard menu · vinyl record fair · open mic night

---

*This scope is a living document. Update it as you build, test, and learn what converts.*

*See also: [Newsletter Preview & Stock Imagery Spec](nearbyweekly-newsletter-preview-spec.md) — the Mailbrew-style inline preview and full image library.*


