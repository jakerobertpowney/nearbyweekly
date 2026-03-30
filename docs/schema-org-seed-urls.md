# Schema.org Event Crawler — Seed URL List

Curated starting points for the `SchemaOrgImporter`. All sites listed are known event publishers likely to carry `@type: Event` JSON-LD markup. Verify each with Google's [Rich Results Test](https://search.google.com/test/rich-results) before committing to the seed list.

**Crawl strategy per seed:** fetch the site's `sitemap.xml`, walk any `sitemap-events` or `sitemap-whats-on` sub-sitemaps, then extract JSON-LD from each event URL. Most major venues and councils publish event-specific sitemaps.

---

## National Aggregators & Tourism Bodies

These are high-value seeds — they aggregate events from many sub-organisations and tend to have well-maintained schema markup.

| Site | URL | Notes |
|---|---|---|
| Visit London | https://www.visitlondon.com/things-to-do/whats-on | Mayor of London backed; strong schema markup |
| Visit Britain | https://www.visitbritain.com/en/things-to-do/events | National tourism body |
| Visit England | https://www.visitengland.com/things-to-do/events | Sub-brand of VisitBritain |
| Time Out London | https://www.timeout.com/london/things-to-do | High event density; check ToS |
| Ents24 | https://www.ents24.com | UK-focused events aggregator; strong schema markup |
| Skiddle | https://www.skiddle.com | UK gig/club/festival aggregator |
| Allevents.in (UK) | https://allevents.in/gb | Broad UK coverage |

---

## National Trust & Heritage

National Trust alone publishes thousands of UK events per year. Both organisations use modern CMS with schema markup.

| Site | URL | Notes |
|---|---|---|
| National Trust | https://www.nationaltrust.org.uk/visit/whats-on | Sitemap includes event URLs per property |
| English Heritage | https://www.english-heritage.org.uk/visit/whats-on | Castles, abbeys, historic sites |
| Historic Environment Scotland | https://www.historicenvironment.scot/visit-a-place/whats-on | Scotland equivalent |
| Cadw (Wales) | https://cadw.gov.wales/visit/whats-on | Welsh heritage sites |
| Royal Parks | https://www.royalparks.org.uk/whats-on | Hyde Park, Richmond Park, etc. |
| Historic Houses | https://www.historichouses.org/houses-and-gardens/events | Private country houses across UK |

---

## Major Arts Centres & Concert Halls

Sites built on Spektrix (widely used UK arts CMS) typically output schema.org markup automatically.

| Site | URL | Notes |
|---|---|---|
| Barbican Centre | https://www.barbican.org.uk/whats-on | Spektrix-based; strong markup |
| Southbank Centre | https://www.southbankcentre.co.uk/whats-on | Royal Festival Hall, Queen Elizabeth Hall |
| Sage Gateshead | https://sagegateshead.com/whats-on | Major North East venue |
| Bridgewater Hall | https://www.bridgewater-hall.co.uk/events | Manchester classical |
| Symphony Hall Birmingham | https://www.thsh.co.uk/whats-on | Birmingham classical + popular |
| Cadogan Hall | https://www.cadoganhall.com/whats-on | London classical |
| Wigmore Hall | https://wigmore-hall.org.uk/whats-on | London chamber music |
| Lighthouse Poole | https://www.lighthousepoole.co.uk/whats-on | South coast arts centre |
| Warwick Arts Centre | https://warwickartscentre.co.uk/whats-on | Midlands |
| Leeds Grand Theatre | https://www.leedsgrandtheatre.com/whats-on | |
| Liverpool Philharmonic | https://www.liverpoolphil.com/whats-on | |
| Colston Hall / Bristol Beacon | https://www.bristolbeacon.org/whats-on | |
| Usher Hall Edinburgh | https://www.usherhall.co.uk/whats-on | |
| Royal Concert Hall Nottingham | https://trchnotts.com/whats-on | |
| De Montfort Hall Leicester | https://www.demontforthall.co.uk/events | |

---

## National Theatres

| Site | URL | Notes |
|---|---|---|
| National Theatre | https://www.nationaltheatre.org.uk/whats-on | Spektrix; excellent markup |
| Royal Opera House | https://www.roh.org.uk/tickets-and-events | |
| Royal Shakespeare Company | https://www.rsc.org.uk/tickets | |
| Shakespeare's Globe | https://www.shakespearesglobe.com/whats-on | |
| Donmar Warehouse | https://www.donmarwarehouse.com/whats-on | |
| Young Vic | https://www.youngvic.org/whats-on | |
| Almeida Theatre | https://almeida.co.uk/whats-on | |
| Bush Theatre | https://www.bushtheatre.co.uk/whats-on | |
| Royal Court Theatre | https://royalcourttheatre.com/whats-on | |
| Soho Theatre | https://sohotheatre.com/whats-on | Comedy + theatre |
| Traverse Theatre Edinburgh | https://www.traverse.co.uk/whats-on | |
| Citizens Theatre Glasgow | https://citz.co.uk/whats-on | |
| Sherman Theatre Cardiff | https://shermantheatre.co.uk/whats-on | |

---

## Music Venues

| Site | URL | Notes |
|---|---|---|
| Ronnie Scott's | https://www.ronniescotts.co.uk/performances | Jazz; known schema markup |
| 100 Club | https://www.the100club.co.uk/events | Oxford Street |
| Roundhouse | https://www.roundhouse.org.uk/whats-on | Camden |
| KOKO | https://www.koko.uk.com/events | Camden |
| Fabric | https://www.fabriclondon.com/events | London club |
| Electric Ballroom | https://www.electric-ballroom.co.uk/events | Camden |
| O2 Academy Brixton | https://www.academymusicgroup.com/o2academybrixton | AMG group |
| O2 Academy Birmingham | https://www.academymusicgroup.com/o2academybirmingham | AMG group |
| Manchester Academy | https://www.manchesteracademy.net/events | |
| Rescue Rooms Nottingham | https://www.rescuerooms.com/events | |
| Brudenell Social Club Leeds | https://brudenellsocialclub.co.uk/events | Indie venue |
| Gorilla Manchester | https://www.thisisgorilla.com/events | |
| SWX Bristol | https://www.swxbristol.com/events | |
| Liquid Rooms Edinburgh | https://www.liquidroom.com/events | |
| O2 Academy Glasgow | https://www.academymusicgroup.com/o2academyglasgow | |

---

## Museums & Galleries

Most major museums publish events with schema markup; often well-maintained sitemaps.

| Site | URL | Notes |
|---|---|---|
| Tate Modern / Tate Britain | https://www.tate.org.uk/whats-on | All four Tate sites |
| V&A Museum | https://www.vam.ac.uk/whats-on | |
| Science Museum | https://www.sciencemuseum.org.uk/see-and-do | |
| Natural History Museum | https://www.nhm.ac.uk/visit/whats-on.html | |
| National Portrait Gallery | https://www.npg.org.uk/whatson | |
| Wellcome Collection | https://wellcomecollection.org/whats-on | Strong schema markup |
| Serpentine Galleries | https://www.serpentinegalleries.org/whats-on | |
| Whitechapel Gallery | https://www.whitechapelgallery.org/events | |
| ICA London | https://www.ica.art/whats-on | |
| Photographers' Gallery | https://thephotographersgallery.org.uk/whats-on | |
| Design Museum | https://designmuseum.org/whats-on | |
| Hayward Gallery | https://www.southbankcentre.co.uk/venues/hayward-gallery | Part of Southbank |
| Tyneside Cinema | https://tynesidecinema.co.uk/whats-on | Newcastle arts cinema |
| HOME Manchester | https://homemcr.org/event | Arts centre + cinema |
| FACT Liverpool | https://www.fact.co.uk/whats-on | Media arts |
| Arnolfini Bristol | https://www.arnolfini.org.uk/whatson | |
| Dundee Contemporary Arts | https://www.dca.org.uk/whats-on | Scotland |

---

## Universities

University events pages are often the best source of free public lectures, debates, and cultural events. Most run on CMS platforms that output schema markup.

| Site | URL | Notes |
|---|---|---|
| UCL | https://www.ucl.ac.uk/events | |
| King's College London | https://www.kcl.ac.uk/events | |
| Imperial College | https://www.imperial.ac.uk/events | |
| LSE | https://www.lse.ac.uk/Events | Strong public lecture programme |
| Oxford University | https://www.ox.ac.uk/event-search | |
| Cambridge University | https://www.cam.ac.uk/events | |
| University of Edinburgh | https://www.ed.ac.uk/events | |
| University of Manchester | https://www.manchester.ac.uk/discover/events | |
| University of Bristol | https://www.bristol.ac.uk/events | |
| University of Leeds | https://www.leeds.ac.uk/events | |
| University of Birmingham | https://www.birmingham.ac.uk/whats-on | |
| Durham University | https://www.durham.ac.uk/events | |
| Warwick University | https://warwick.ac.uk/about/events | |

---

## Local Authority Event Pages

Most UK councils run Drupal or similar CMS that supports schema markup. These are particularly strong for community and free events that never appear on commercial platforms.

| Site | URL | Notes |
|---|---|---|
| Manchester City Council | https://www.manchester.gov.uk/whats-on | |
| Leeds City Council | https://www.leeds.gov.uk/leisure-and-culture/whats-on | |
| Birmingham City Council | https://www.birmingham.gov.uk/whats-on | |
| Bristol City Council | https://www.bristol.gov.uk/whats-on | |
| Liverpool City Council | https://liverpool.gov.uk/whats-on | |
| Sheffield City Council | https://www.sheffield.gov.uk/whats-on | |
| Newcastle City Council | https://www.newcastle.gov.uk/services/arts-culture-and-tourism/events | |
| Edinburgh City Council | https://edinburgh.org/events | VisitEdinburgh sub-site |
| Glasgow Life | https://www.glasgowlife.org.uk/events | Glasgow's events body |
| Cardiff Council | https://www.visitcardiff.com/events | |
| Nottingham City Council | https://www.nottinghamcity.gov.uk/arts-and-culture/events | |
| Leicester City Council | https://www.visitleicester.info/events | |
| Cornwall Council | https://www.visitcornwall.com/whats-on | Tourist-facing events page |
| Brighton & Hove | https://www.visitbrighton.com/whats-on | |
| Oxford City | https://www.experienceoxfordshire.org/events | |

---

## Comedy Clubs

| Site | URL | Notes |
|---|---|---|
| Comedy Store London | https://www.thecomedystore.co.uk/shows | |
| Soho Theatre (Comedy) | https://sohotheatre.com/comedy | Also in theatre list |
| Up the Creek | https://www.up-the-creek.com/events | Greenwich |
| Highlight Comedy | https://www.highlight-comedy.co.uk/events | Bristol based |
| Glee Club | https://www.glee.co.uk/comedy | Birmingham, Cardiff, Glasgow |
| Comedy Café | https://www.comedycafe.co.uk/events | London |
| Jongleurs | https://www.jongleurs.com/events | UK chain |

---

## Food, Markets & Outdoor Events

| Site | URL | Notes |
|---|---|---|
| Borough Market | https://boroughmarket.org.uk/events | London; good schema markup |
| Street Feast | https://www.streetfeast.com/events | London night markets |
| Real Food Festival | https://www.realfoodfestival.co.uk/events | |
| Farmers' Market Association | https://farmersmarkets.net/find-a-market | Directory of UK markets |
| Lost Village / Secret Garden | https://www.thelostvillage.co.uk | Festival; schema markup |
| Wilderness Festival | https://www.wildernessfestival.com | |
| Wilderness Hay Festival | https://www.hayfestival.com/wales/en-gb/programme.aspx | Literary |
| Edinburgh Food Festival | https://www.edinburghfoodfestival.com | |

---

## Sports & Leisure

| Site | URL | Notes |
|---|---|---|
| Parkrun | https://www.parkrun.org.uk/events | Free weekly 5ks — events with location data |
| British Cycling | https://www.britishcycling.org.uk/events | |
| UK Athletics | https://www.britishathletics.org.uk/events | |
| British Triathlon | https://www.britishtriathlon.org/events | |
| Yoga Alliance UK | https://www.yogaalliance.co.uk/events | |

---

## Kids & Family

| Site | URL | Notes |
|---|---|---|
| Kids in Museums | https://kidsinmuseums.org.uk/events | |
| Discover Children's Story Centre | https://www.discover.org.uk/whats-on | Stratford, London |
| Eureka! National Children's Museum | https://www.eureka.org.uk/whats-on | Halifax |
| Roald Dahl Museum | https://www.roalddahl.com/museum/whats-on | Great Missenden |
| Mudchute Farm | https://www.mudchute.org/events | London |

---

## Implementation Notes

**CMS platforms that auto-generate schema.org Event markup** — when you encounter a site on one of these, schema markup is almost guaranteed:

- **Spektrix** — used by hundreds of UK arts venues; outputs JSON-LD automatically
- **Tessitura** — used by Royal Opera House, National Theatre, and major orchestras
- **WordPress + The Events Calendar plugin** — very common for smaller venues and councils
- **Squarespace** (Events pages) — outputs Event schema automatically
- **Drupal** (with Schema.org module) — common in UK councils and universities

**Sitemaps to check first:** `sitemap.xml`, `sitemap-events.xml`, `sitemap-whats-on.xml`, `event-sitemap.xml`. Most venues will have one of these.

**robots.txt:** Always check before crawling. Sites that explicitly disallow crawlers should be removed from the seed list, even if their markup is attractive.

**Crawl rate:** Use a minimum 2-second delay between requests per domain. These are small organisations — aggressive crawling is both impolite and likely to get the IP blocked.

---

## Verification Checklist

Before adding any URL to production config, run through:

1. Paste an event page URL into [Google's Rich Results Test](https://search.google.com/test/rich-results) — confirms schema markup is present and valid
2. Check `robots.txt` at the root domain
3. Skim the site's Terms of Use for crawling prohibitions
4. Confirm events include UK postcodes (required for geocoding and distance matching)
5. Check `sitemap.xml` to understand the structure and volume of event URLs

Sources: [Schema.org Event type](https://schema.org/Event) · [Google Event structured data docs](https://developers.google.com/search/docs/appearance/structured-data/event) · [CultureSuite on structured data for venues](https://www.culturesuite.co/resources/articles/structured-data-for-cultural-venues-why-it-matters-and-how-peppered-handles-it-all)
