# NearbyWeekly — Floating Hero Cards Design Spec

> **Inspiration:** Greenhouse's landing page floats UI elements around its central headline to show the product in context without a dedicated screenshot section. For NearbyWeekly, we apply the same pattern — but instead of software UI, we float real event cards from the actual weekly newsletter.

---

## The Concept

The hero headline sits at the centre of the page. Around it, 4–6 event cards are scattered at slight angles — as if they've just landed on the page. Each card is a mini version of exactly what arrives in the subscriber's inbox on Thursday morning: a photo, an event name, a venue, a date, a distance chip, and a category badge.

This achieves two things at once: it's the hero visual **and** the product demo.

```
┌─────────────────────────────────────────────────────────────────────┐
│  ◆ NearbyWeekly                              [Build My Picks]       │
│─────────────────────────────────────────────────────────────────────│
│                                                                     │
│  ┌──────────────┐           ┌──────────────────────────────────┐   │
│  │ 📸           │           │  📍 Borough Market               │   │
│  │ 🎭 Comedy    │           │     Night Market                 │   │
│  │ Monkey Barrel│           │  🍕 Food & Drink                  │   │
│  │ Sat 29 Mar   │           │  Fri 28 Mar  · 0.4 miles        │   │
│  │ 📍 1.2 mi    │           └──────────────────────────────────┘   │
│  └──────────────┘                                                   │
│                                                                     │
│              YOUR WEEKEND,                                          │
│                 SORTED.                                             │
│                                                                     │
│       A weekly email of events you'd                                │
│       actually leave the house for.                                 │
│                                                                     │
│       ┌────────────────────────┐                                    │
│       │ 📍 Enter your postcode │  [Show me what's on →]            │
│       └────────────────────────┘                                    │
│                                                                     │
│  ┌──────────────────────────────────┐    ┌──────────────────────┐  │
│  │ 📸                               │    │ 📸                   │  │
│  │ 🎵 Jazz Café Sessions            │    │ 🥾 Box Hill          │  │
│  │ Tue 1 Apr  · 3.4 miles           │    │    Sunset Hike       │  │
│  └──────────────────────────────────┘    │ 🌿 Outdoors          │  │
│                                          │ Sun 6 Apr · 12 mi   │  │
│                    ┌──────────────────┐  └──────────────────────┘  │
│                    │ 📸               │                             │
│                    │ 🧘 Sunrise Yoga  │                             │
│                    │ Wed 2 Apr        │                             │
│                    │ 📍 2.1 miles     │                             │
│                    └──────────────────┘                             │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Card Design

### Individual Card Anatomy

Each floating card is a condensed version of the newsletter event card:

```
┌──────────────────────────────┐
│ ┌──────────────────────────┐ │  ← Photo (16:9, rounded top corners)
│ │       📸 event photo     │ │
│ └──────────────────────────┘ │
│                              │
│  [🎭 Comedy]                 │  ← Category badge (pill, forest green bg)
│                              │
│  Monkey Barrel               │  ← Event title (Poppins semi-bold)
│  Comedy Club                 │
│                              │
│  Sat 29 Mar                  │  ← Date (Instrument Sans, muted)
│  📍 1.2 miles away           │  ← Distance chip
│                              │
└──────────────────────────────┘
```

- **Width:** 200–240px (desktop), 160px (mobile)
- **Border radius:** 16px
- **Background:** white `#ffffff`
- **Border:** 1px solid `#e8ede9` (very subtle green-tinted grey)
- **Drop shadow:** `0 4px 20px rgba(26, 53, 40, 0.12)` — a green-tinted shadow, not grey
- **Photos:** 16:9 ratio, rounded top corners matching card radius

### Tilt & Placement

Cards should feel naturally scattered, not rigidly grid-aligned. Suggested rotations:

| Card position | Rotation | Size |
|---|---|---|
| Top left | −4° | Medium (220px) |
| Top right | +3° | Small (200px) |
| Bottom left | +2° | Large (240px) |
| Bottom right | −5° | Medium (220px) |
| Centre bottom (optional 5th) | +1° | Small (190px) |

On hover, cards should subtly un-tilt and lift (`transform: rotate(0deg) translateY(-4px)`) — a satisfying "pick up a card" effect.

---

## Layout Zones

The page divides into three horizontal zones:

```
┌─────────────────────────────────────────┐
│  ZONE A — Top cards                      │  ← Cards scattered above headline
│  (left-leaning, slightly higher)        │
├─────────────────────────────────────────┤
│  ZONE B — Headline + CTA                │  ← Centred copy, postcode input
│  (vertically centred)                   │
├─────────────────────────────────────────┤
│  ZONE C — Bottom cards                  │  ← Cards scattered below CTA
│  (right-leaning, slightly lower)        │
└─────────────────────────────────────────┘
```

Cards in Zone A appear to float into the frame from the top as the page loads (entrance animation: fade + slide down 20px). Zone C cards fade + slide up.

**Headline and CTA are always legible** — no card should overlap the text. Cards stay in the outer 35% of the viewport width on each side.

---

## Background

**White. Flat. No gradient.**

The forest green depth comes entirely from the cards' shadows and the dark headline text — not from the background. This keeps the page feeling light, editorial, and modern.

The only background variation is a very subtle noise/grain texture at ~3% opacity (optional — adds a slight premium tactility without looking grainy).

---

## Colour Application in the Hero

| Element | Colour |
|---|---|
| Page background | `#ffffff` |
| Headline "YOUR WEEKEND, SORTED." | `#0f1a14` (near-black, green-tinted) |
| Subheading copy | `#4a6355` (mid forest — readable but softer) |
| Category badges | Forest green `#1a3528` text on `#e8f0eb` bg |
| Distance chips | `#6b8c7a` text on `#f2f7f4` bg |
| Event titles | `#0f1a14` |
| Dates | `#6b8c7a` |
| CTA button | `#1a3528` bg, white text |
| CTA button hover | `#2d5a45` bg |
| Postcode input | White bg, `#1a3528` border on focus |
| Card shadows | `rgba(26, 53, 40, 0.12)` |

---

## Animation Sequence

On page load, a staggered entrance to give the cards a "landing" feel:

1. **0ms** — Headline fades in
2. **100ms** — Subheading fades in
3. **200ms** — Postcode input fades in
4. **300ms** — Top-left card slides in from top-left
5. **400ms** — Top-right card slides in from top-right
6. **500ms** — Bottom-left card slides up
7. **600ms** — Bottom-right card slides up
8. **700ms** — Optional 5th card fades in

Each animation uses `ease-out` with a 400ms duration. No bounce — that would feel too playful for the editorial direction of forest green.

On scroll, the cards subtly shift using a **parallax effect** — Zone A cards drift upward slightly faster than the scroll speed, Zone C cards drift slower. This gives depth without being distracting.

---

## Mobile Behaviour

On screens below 768px, the floating layout collapses. Instead:

- The headline and CTA stack vertically in the centre
- Below the CTA, a **horizontal scroll strip** of 2–3 event cards appears — same cards, but laid out in a row the user can swipe through
- Cards are no longer tilted on mobile
- Drop shadows are reduced to avoid visual noise on small screens

```
┌──────────────────────────────┐
│  ◆ NearbyWeekly              │
│                              │
│  YOUR WEEKEND,               │
│  SORTED.                     │
│                              │
│  A weekly email of events    │
│  you'd actually leave the    │
│  house for.                  │
│                              │
│  ┌──────────────────────┐    │
│  │ 📍 Enter postcode    │    │
│  └──────────────────────┘    │
│  [Show me what's on →]       │
│                              │
│  ──────────────────────────  │
│  Swipe to see what you'd get │
│                              │
│  ┌────────┐ ┌────────┐ ┌──   │
│  │ 📸     │ │ 📸     │ │ …   │
│  │ Comedy │ │ Market │ │     │
│  │ 1.2 mi │ │ 0.4 mi │ │     │
│  └────────┘ └────────┘ └──   │
└──────────────────────────────┘
```

---

## Stock Photo Assignment for Hero Cards

Use these specific images (from the NearbyWeekly image library) for the 5 hero cards:

| Card | Category | Photo |
|---|---|---|
| Top left | Comedy | [Audience laughing at a show](https://unsplash.com/s/photos/comedy-show-audience) |
| Top right | Food & Drink | [Bustling food market with shoppers](https://unsplash.com/photos/people-are-shopping-at-a-bustling-food-market-k64WpRyUIPs) |
| Bottom left | Music | [Concert crowd with hands raised](https://unsplash.com/photos/crowd-with-hands-raised-at-a-concert-with-stage-lights-b7gi_rqKxcQ) |
| Bottom right | Outdoors | [Friends at an outdoor event](https://unsplash.com/photos/group-of-friends-having-fun-and-relaxing-at-an-amusement-theme-park-happy-time-with-young-team-d6tOLK3rvlI) |
| Centre bottom | Wellness | [Group exercise outdoors](https://unsplash.com/photos/group-of-people-raising-their-hands-GvF7RkA-E9Q) |

Photos should be downloaded at 400px width (cards are small — no need for full res), converted to WebP, stored at `public/img/hero/`.

---

## Implementation Notes

### Tech approach
The floating layout can be achieved with CSS `position: absolute` cards inside a `position: relative` hero container, using percentage-based positioning to remain responsive.

```css
.hero-container {
  position: relative;
  min-height: 100vh;
  background: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
}

.hero-card {
  position: absolute;
  width: 220px;
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(26, 53, 40, 0.12);
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.hero-card:hover {
  transform: rotate(0deg) translateY(-4px) !important;
  box-shadow: 0 8px 32px rgba(26, 53, 40, 0.18);
}

.hero-card--top-left  { top: 12%; left: 4%; transform: rotate(-4deg); }
.hero-card--top-right { top: 8%;  right: 5%; transform: rotate(3deg); }
.hero-card--bot-left  { bottom: 15%; left: 6%; transform: rotate(2deg); }
.hero-card--bot-right { bottom: 10%; right: 4%; transform: rotate(-5deg); }
```

### Vue component
This should be a dedicated `HeroCards.vue` component that accepts an array of event objects as props. The parent `Welcome.vue` passes in the hardcoded sample events. Later, this could be wired to pull the most recent newsletter items from the backend.

### Accessibility
- Cards must have `aria-hidden="true"` — they are decorative, not navigational
- Hover animations must respect `prefers-reduced-motion` — if set, skip all transitions and entrance animations

---

## Integration with Main Scope

This spec replaces the **right-side mockup** described in Section 1 (Hero) of the main landing page scope. The postcode input and headline copy remain unchanged. The change is purely in how the "product preview" is presented — from a flat newsletter screenshot to living event cards surrounding the headline.

*See also: [Landing Page Design Scope](nearbyweekly-landing-page-design-scope.md) · [Newsletter Preview Spec](nearbyweekly-newsletter-preview-spec.md)*
