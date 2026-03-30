# NearbyWeekly — Inline Newsletter Preview & Stock Imagery Spec

> **Inspiration:** Mailbrew places a live, scrollable preview of today's digest directly below the hero headline — inside a fake email client frame. Visitors can read actual content before signing up. This is their single most persuasive element.

## What We're Building

A full-height, scrollable newsletter preview embedded directly into the landing page, sitting immediately below the hero heading and CTA. Not a thumbnail. Not a screenshot. A real, rendered version of the latest (or a curated sample) NearbyWeekly newsletter, inside a styled email client frame.

This replaces the smaller newsletter mockup currently described in the hero wireframe. The hero copy and postcode CTA remain above it — the preview lives directly underneath, so the first thing a visitor scrolls into is the actual product.

---

## Wireframe

```
┌─────────────────────────────────────────────────────────────────┐
│  ✦ nearbyweekly                                 [Build My Picks]   │
│─────────────────────────────────────────────────────────────────│
│                                                                 │
│              YOUR WEEKEND, SORTED. 🎉                           │
│                                                                 │
│    A weekly email of events you'd                               │
│    actually leave the house for.                                │
│                                                                 │
│    ┌──────────────────────────────┐                             │
│    │ 📍 Enter your postcode       │  [Show me what's on →]     │
│    └──────────────────────────────┘                             │
│                                                                 │
│    ✓ 100% free  ✓ Unsubscribe anytime  ✓ No spam, ever        │
│                                                                 │
│═════════════════════════════════════════════════════════════════│
│                                                                 │
│         ┌── Via email ──┬── In the browser ──┐                  │
│         │   (active)    │    (inactive)       │                  │
│         └───────────────┴────────────────────┘                  │
│                                                                 │
│    ┌────────────────────────────────────────────────────────┐   │
│    │ ┌──────────────────────────────────────────────────┐   │   │
│    │ │  M  Gmail              ✦ nearbyweekly    1 of 32    │   │   │
│    │ ├──────────────────────────────────────────────────┤   │   │
│    │ │                                                  │   │   │
│    │ │           ✦ nearbyweekly                            │   │   │
│    │ │                                                  │   │   │
│    │ │   Here's what's on near SE1 this week            │   │   │
│    │ │                                                  │   │   │
│    │ │   ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━         │   │   │
│    │ │                                                  │   │   │
│    │ │   🎉 THIS WEEKEND                                │   │   │
│    │ │                                                  │   │   │
│    │ │   ┌──────────────┐  ┌──────────────┐             │   │   │
│    │ │   │ ┌──────────┐ │  │ ┌──────────┐ │             │   │   │
│    │ │   │ │ 📸 crowd │ │  │ │ 📸 market│ │             │   │   │
│    │ │   │ │ laughing  │ │  │ │ stalls   │ │             │   │   │
│    │ │   │ └──────────┘ │  │ └──────────┘ │             │   │   │
│    │ │   │ 🎭 Comedy    │  │ 🍕 Food      │             │   │   │
│    │ │   │              │  │              │             │   │   │
│    │ │   │ Monkey Barrel│  │ Borough Mkt  │             │   │   │
│    │ │   │ Comedy Club  │  │ Night Market │             │   │   │
│    │ │   │ Sat 29 Mar   │  │ Fri 28 Mar   │             │   │   │
│    │ │   │ 📍 1.2 miles │  │ 📍 0.4 miles │             │   │   │
│    │ │   │              │  │              │             │   │   │
│    │ │   │ [Get Tkts →] │  │ [Get Tkts →] │             │   │   │
│    │ │   └──────────────┘  └──────────────┘             │   │   │
│    │ │                                                  │   │   │
│    │ │   🗓️ THIS WEEK                                   │   │   │
│    │ │                                                  │   │   │
│    │ │   ┌──────────────┐  ┌──────────────┐             │   │   │
│    │ │   │ ┌──────────┐ │  │ ┌──────────┐ │             │   │   │
│    │ │   │ │ 📸 band  │ │  │ │ 📸 yoga  │ │             │   │   │
│    │ │   │ │ on stage │ │  │ │ in park  │ │             │   │   │
│    │ │   │ └──────────┘ │  │ └──────────┘ │             │   │   │
│    │ │   │ 🎵 Music     │  │ 🧘 Wellness  │             │   │   │
│    │ │   │              │  │              │             │   │   │
│    │ │   │ Jazz Café    │  │ Sunrise Yoga │             │   │   │
│    │ │   │ Sessions     │  │ Clapham Com. │             │   │   │
│    │ │   │ Tue 1 Apr    │  │ Wed 2 Apr    │             │   │   │
│    │ │   │ 📍 3.4 miles │  │ 📍 2.1 miles │             │   │   │
│    │ │   │              │  │              │             │   │   │
│    │ │   │ [Get Tkts →] │  │ [Get Tkts →] │             │   │   │
│    │ │   └──────────────┘  └──────────────┘             │   │   │
│    │ │                                                  │   │   │
│    │ │   🔜 COMING UP                                   │   │   │
│    │ │                                                  │   │   │
│    │ │   ┌──────────────┐  ┌──────────────┐             │   │   │
│    │ │   │ ┌──────────┐ │  │ ┌──────────┐ │             │   │   │
│    │ │   │ │ 📸 kids  │ │  │ │ 📸 hike  │ │             │   │   │
│    │ │   │ │ laughing │ │  │ │ scenic   │ │             │   │   │
│    │ │   │ └──────────┘ │  │ └──────────┘ │             │   │   │
│    │ │   │ 👨‍👩‍👧 Family   │  │ 🥾 Outdoors  │             │   │   │
│    │ │   │              │  │              │             │   │   │
│    │ │   │ Kew Gardens  │  │ Box Hill     │             │   │   │
│    │ │   │ Easter Trail │  │ Sunset Hike  │             │   │   │
│    │ │   │ Sat 5 Apr    │  │ Sun 6 Apr    │             │   │   │
│    │ │   │ 📍 5.8 miles │  │ 📍 12 miles  │             │   │   │
│    │ │   │              │  │              │             │   │   │
│    │ │   │ [Get Tkts →] │  │ [Get Tkts →] │             │   │   │
│    │ │   └──────────────┘  └──────────────┘             │   │   │
│    │ │                                                  │   │   │
│    │ │   ─────────────────────────────────               │   │   │
│    │ │   Update preferences · Unsubscribe               │   │   │
│    │ │                                                  │   │   │
│    │ └──────────────────────────────────────────────────┘   │   │
│    └────────────────────────────────────────────────────────┘   │
│                                                                 │
│                  This could be YOUR inbox 👆                    │
│                                                                 │
│                    [Build My Weekly Picks]                       │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## How This Differs from Section 3 (Newsletter Preview) in the Main Scope

Section 3 in the landing page design scope describes a mid-page newsletter preview. This spec **replaces and upgrades** that concept:

| Original Section 3 | This Spec |
|---|---|
| Sits midway down the page | Lives directly under the hero — first thing you scroll into |
| Shows 4 event cards in a compact mockup | Shows the **full newsletter** with all time-bucketed sections (Weekend, This Week, Coming Up) |
| Static mockup | Scrollable container with real email client chrome |
| No tab switching | "Via email" / "In the browser" tabs (like Mailbrew) |
| No event photos | **Every event card has a stock photo** |

---

## Design Spec

### The Email Client Frame

Wrap the newsletter in a container that looks like a real inbox:

- **Top bar:** Gmail-style chrome — the "M" logo, "Gmail" label, sender name "✦ nearbyweekly", and a "1 of 32" pagination hint (suggests this is a regular, ongoing thing)
- **Rounded corners** (16px) on the outer container, subtle drop shadow (`0 8px 32px rgba(0,0,0,0.12)`)
- **Max height:** ~600px on desktop, with inner scroll. The newsletter content overflows so visitors can scroll through it — just like reading a real email
- **Background:** white inside the frame, light sage (`#f2f7f4`) outside

### The Tab Bar

Directly above the email frame, two tabs:

- **"Via email"** (active, forest green `#1a3528` underline) — shows the newsletter inside the Gmail frame
- **"In the browser"** (inactive, grey) — switches to a browser-chrome frame showing the same content as a web page

This is a nice touch borrowed from Mailbrew. It subtly communicates "you can read this however you want" and adds a layer of polish. The content inside is identical — only the frame changes.

### Event Cards Inside the Preview

Each event card within the preview must include:

- **A stock photo** (landscape format, ~200×120px) at the top of each card
- **Category badge** (e.g. "🎭 Comedy", "🍕 Food") in a coloured pill
- **Event title** in bold Poppins
- **Venue name** and **formatted date** in lighter body text
- **Distance chip** ("📍 1.2 miles away") in a rounded badge
- **CTA button** ("Get Tickets →") in forest green `#1a3528`

Cards are arranged in a **2-column grid** within the email, grouped under time-bucket headers (This Weekend, This Week, Coming Up).

### Scroll Behaviour

- The outer page scrolls normally
- The email frame has `overflow-y: auto` with a max-height, so visitors can scroll within the preview without scrolling the whole page
- A subtle **fade-to-white** at the bottom of the frame hints that there's more content below (encourages scrolling)
- On mobile: the frame takes full width, and the inner scroll becomes the natural scroll (no nested scroll weirdness)

---

## Stock Photography for Event Cards

Each event card in the preview needs a real photo to sell the vibe. Use these curated Unsplash images — all free to use, all showing people having fun:

### 🎭 Comedy / Entertainment
| Use for | Image |
|---|---|
| Comedy night card | [Audience laughing at a show](https://unsplash.com/s/photos/comedy-show-audience) |
| Theatre card | [Theatre stage with dramatic lighting](https://unsplash.com/photos/pPWbLktpWZM) |

### 🎵 Music / Concerts
| Use for | Image |
|---|---|
| Live gig card | [Concert crowd with hands raised, stage lights](https://unsplash.com/photos/silhouettes-of-festival-concert-crowd-in-front-of-bright-stage-lights-unrecognizable-people-and-colorful-effects-JCGE51UEL2A) |
| Festival card | [Crowd at concert with colourful lighting](https://unsplash.com/photos/crowd-with-hands-raised-at-a-concert-with-stage-lights-b7gi_rqKxcQ) |
| Intimate gig card | [Large crowd at a live music event](https://unsplash.com/photos/a-large-crowd-of-people-at-a-concert-ilASS6yjx8w) |

### 🍕 Food & Drink / Markets
| Use for | Image |
|---|---|
| Food market card | [Bustling food market with shoppers](https://unsplash.com/photos/people-are-shopping-at-a-bustling-food-market-k64WpRyUIPs) |
| Night market card | [People dining at a busy food market](https://unsplash.com/photos/people-dine-at-a-busy-food-market-HXeHsf9SPUQ) |
| Street food card | [Market stall with vendors and goods](https://unsplash.com/photos/a-busy-market-with-vendors-and-goods-fLID9iiGPrE) |

### 🧘 Health & Fitness
| Use for | Image |
|---|---|
| Outdoor yoga card | [Group exercise session outdoors](https://unsplash.com/photos/group-of-people-raising-their-hands-GvF7RkA-E9Q) |
| Park run card | [People exercising on a beach at sunrise](https://unsplash.com/photos/people-exercising-on-seashore-during-daytime-I749-lKHHJ4) |

### 👨‍👩‍👧 Family Days Out
| Use for | Image |
|---|---|
| Family event card | [Kids playing outdoors, laughing](https://unsplash.com/photos/_jA8Vu7WTgM) |
| Kids activity card | [Group of kids having fun near water](https://unsplash.com/photos/group-of-kids-having-a-conversation-near-body-of-water-pIsHOl77zzA) |

### 🥾 Outdoors & Nature
| Use for | Image |
|---|---|
| Hiking card | Search: [UK hiking trail scenic views](https://unsplash.com/s/photos/hiking-uk) |
| Outdoor adventure card | [Friends at an outdoor event, having fun](https://unsplash.com/photos/group-of-friends-having-fun-and-relaxing-at-an-amusement-theme-park-happy-time-with-young-team-d6tOLK3rvlI) |

### 💻 Tech & Professional
| Use for | Image |
|---|---|
| Meetup card | Search: [Tech meetup networking event](https://unsplash.com/s/photos/tech-meetup) |

> **Image treatment:** All photos should be cropped to a consistent 16:9 landscape ratio. Apply a very subtle warm colour overlay (`rgba(26, 53, 40, 0.04)`) to tie them into the orange brand palette without looking filtered.

---

## Full-Page Image Placements (Beyond the Newsletter Preview)

Stock photos aren't just for the email preview — they should appear across the landing page to reinforce the "fun" feeling:

### Hero Background
A large, blurred, warm-toned photo of a crowd enjoying an event — just enough to add texture and energy behind the headline. Apply a strong white-to-transparent gradient overlay so the text remains readable.

**Suggested:** [Festival crowd with warm stage lighting](https://unsplash.com/photos/silhouettes-of-festival-concert-crowd-in-front-of-bright-stage-lights-unrecognizable-people-and-colorful-effects-JCGE51UEL2A) at ~15% opacity with a dark forest green overlay.

### Interest Category Cards (Section 4)
Each of the 7 category cards gets a background photo with a dark forest green overlay `rgba(26,53,40,0.65)` and white text:

| Category | Photo |
|---|---|
| Music | Festival crowd with hands up and colourful lights |
| Food & Drink | [Bustling food market](https://unsplash.com/photos/people-are-shopping-at-a-bustling-food-market-k64WpRyUIPs) |
| Arts & Entertainment | [Theatre stage](https://unsplash.com/photos/pPWbLktpWZM) |
| Health & Fitness | [Group exercise outdoors](https://unsplash.com/photos/group-of-people-raising-their-hands-GvF7RkA-E9Q) |
| Family | [Kids playing and laughing](https://unsplash.com/photos/_jA8Vu7WTgM) |
| Outdoors & Nature | Scenic UK trail or countryside walk |
| Tech & Professional | Buzzy co-working event or conference |

### Social Proof Section (Section 5)
A subtle background strip showing a montage of small, circular-cropped event photos — creates a collage effect that reinforces "look at all these things happening."

### Final CTA Section (Section 7)
Same warm background treatment as the hero — energetic event photo at low opacity behind the closing CTA card.

---

## Implementation Notes

### Data Source for the Preview
Two options:

1. **Static mock** (recommended for v1) — hand-build the preview HTML with curated event data and stock photos. Update it monthly to keep it feeling fresh. This is what Mailbrew does.

2. **Dynamic from latest newsletter** — pull the most recent `newsletter_run` with status `sent`, load its `newsletter_items` with associated events, and render the preview using the same Blade partial as the actual email. More work, but automatically stays current.

**Recommendation:** Start with the static mock. It's faster to ship, you control the quality of the sample data, and you can cherry-pick the most appealing events. Move to dynamic later once you have enough high-quality newsletter sends.

### Image Optimisation
- Download chosen Unsplash images at 640px width (not full resolution)
- Convert to WebP format
- Lazy-load all images below the fold
- Use `aspect-ratio: 16/9` CSS to prevent layout shift during load
- Store in `public/img/landing/` with descriptive filenames (e.g. `comedy-crowd.webp`, `food-market.webp`)

### Responsive Behaviour
- **Desktop (>1024px):** Email frame is ~680px wide, centred, with generous padding
- **Tablet (768–1024px):** Frame stretches to ~90% width
- **Mobile (<768px):** Frame goes full-width, no outer padding. The Gmail chrome simplifies (hides "1 of 32"). Event cards stack to single column inside the email. Tab bar remains but with smaller text

---

## Updated Build Order (Revised)

With this spec, the overall landing page build order becomes:

1. **Hero copy + postcode CTA** — the conversion mechanism
2. **Inline newsletter preview** ← THIS SPEC — the highest-impact visual element
3. **Stock photo sourcing + optimisation** — download, crop, convert to WebP
4. **How It Works** — three-step explainer
5. **Interest category cards** — with background photos
6. **Social Proof** — stats + testimonials
7. **FAQ accordion** — objection handling
8. **Final CTA** — closing section
9. **Polish** — animations, mobile QA, Lighthouse audit
