<?php

namespace App\Http\Controllers;

use App\Mail\WeeklyNewsletterMail;
use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class NewsletterDemoController extends Controller
{
    public function __invoke(Request $request): WeeklyNewsletterMail
    {
        $fakeUser = new User([
            'name' => 'Demo User',
            'email' => 'demo@nearbyweekly.co.uk',
            'postcode' => 'SE1 7PB',
        ]);

        // Find next Saturday and Sunday
        $now = now();
        $nextSaturday = $now->copy()->next(Carbon::SATURDAY);
        $nextSunday = $now->copy()->next(Carbon::SUNDAY);
        $nextTuesday = $now->copy()->next(Carbon::TUESDAY);
        $nextWednesday = $now->copy()->next(Carbon::WEDNESDAY);
        $nextThursday = $now->copy()->next(Carbon::THURSDAY);
        $nextFriday = $now->copy()->next(Carbon::FRIDAY);
        $twoWeeksOut = $now->copy()->addWeeks(2)->next(Carbon::SATURDAY);
        $twoWeeksOutSun = $now->copy()->addWeeks(2)->next(Carbon::SUNDAY);
        $threeWeeksOut = $now->copy()->addWeeks(3)->next(Carbon::SATURDAY);
        $threeWeeksOutSun = $now->copy()->addWeeks(3)->next(Carbon::SUNDAY);

        // ── WEEKEND bucket ──────────────────────────────────────────────────
        $weekendEvents = [
            new Event([
                'title' => 'Monkey Barrel Comedy Club',
                'venue_name' => 'Monkey Barrel',
                'city' => 'Old Street, EC1',
                'starts_at' => $nextSaturday->copy()->setTime(20, 0),
                'image_url' => '/img/landing/comedy-crowd.webp',
                'category' => 'comedy',
                'url' => 'https://example.com',
                'matched_interest_ids' => [],
                'score_manual' => 0,
            ]),
            new Event([
                'title' => 'Borough Market Night Market',
                'venue_name' => 'Borough Market',
                'city' => 'London Bridge, SE1',
                'starts_at' => $nextSaturday->copy()->setTime(18, 0),
                'image_url' => '/img/landing/food-market.webp',
                'category' => 'food-and-drink',
                'url' => 'https://example.com',
                'matched_interest_ids' => [],
                'score_manual' => 0,
            ]),
            new Event([
                'title' => "The Coral — Live at Shepherd's Bush Empire",
                'venue_name' => "Shepherd's Bush Empire",
                'city' => "Shepherd's Bush, W12",
                'starts_at' => $nextSaturday->copy()->setTime(19, 30),
                'image_url' => '/img/landing/jazz-cafe.webp',
                'category' => 'concerts',
                'url' => 'https://example.com',
                'matched_interest_ids' => [],
                'score_manual' => 0,
            ]),
            new Event([
                'title' => 'Maltby Street Market',
                'venue_name' => 'Maltby Street',
                'city' => 'Bermondsey, SE1',
                'starts_at' => $nextSunday->copy()->setTime(10, 0),
                'image_url' => '/img/landing/food-market.webp',
                'category' => 'food-and-drink',
                'url' => 'https://example.com',
                'matched_interest_ids' => [],
                'score_manual' => 0,
            ]),
        ];

        // ── WEEK bucket ─────────────────────────────────────────────────────
        $weekEvents = [
            new Event([
                'title' => 'Jazz Café Sessions',
                'venue_name' => 'Jazz Café',
                'city' => 'Camden, NW1',
                'starts_at' => $nextTuesday->copy()->setTime(20, 0),
                'image_url' => '/img/landing/jazz-cafe.webp',
                'category' => 'concerts',
                'url' => 'https://example.com',
                'matched_interest_ids' => [],
                'score_manual' => 0,
            ]),
            new Event([
                'title' => 'Sunrise Yoga — Clapham',
                'venue_name' => 'Clapham Common',
                'city' => 'Clapham, SW4',
                'starts_at' => $nextWednesday->copy()->setTime(7, 0),
                'image_url' => '/img/landing/sunrise-yoga.webp',
                'category' => 'wellness',
                'url' => 'https://example.com',
                'matched_interest_ids' => [],
                'score_manual' => 0,
            ]),
            new Event([
                'title' => 'The Play That Goes Wrong',
                'venue_name' => 'Duchess Theatre',
                'city' => 'Covent Garden, WC2',
                'starts_at' => $nextThursday->copy()->setTime(19, 30),
                'image_url' => '/img/landing/comedy-crowd.webp',
                'category' => 'theatre',
                'url' => 'https://example.com',
                'matched_interest_ids' => [],
                'score_manual' => 0,
            ]),
            new Event([
                'title' => 'Science Museum Lates',
                'venue_name' => 'Science Museum',
                'city' => 'South Kensington, SW7',
                'starts_at' => $nextFriday->copy()->setTime(18, 45),
                'image_url' => '/img/landing/family-day-out.webp',
                'category' => 'family-days-out',
                'url' => 'https://example.com',
                'matched_interest_ids' => [],
                'score_manual' => 0,
            ]),
        ];

        // ── COMING SOON bucket ──────────────────────────────────────────────
        $comingSoonEvents = [
            new Event([
                'title' => 'Kew Gardens Easter Trail',
                'venue_name' => 'Kew Gardens',
                'city' => 'Kew, TW9',
                'starts_at' => $twoWeeksOut->copy()->setTime(10, 0),
                'image_url' => '/img/landing/family-day-out.webp',
                'category' => 'family-days-out',
                'url' => 'https://example.com',
                'matched_interest_ids' => [],
                'score_manual' => 0,
            ]),
            new Event([
                'title' => 'Box Hill Sunset Hike',
                'venue_name' => 'Box Hill',
                'city' => 'Dorking, Surrey',
                'starts_at' => $twoWeeksOutSun->copy()->setTime(17, 0),
                'image_url' => '/img/landing/hiking-trail.webp',
                'category' => 'hiking',
                'url' => 'https://example.com',
                'matched_interest_ids' => [],
                'score_manual' => 0,
            ]),
            new Event([
                'title' => 'Richmond Park Morning Run',
                'venue_name' => 'Richmond Park',
                'city' => 'Richmond, TW10',
                'starts_at' => $threeWeeksOut->copy()->setTime(8, 0),
                'image_url' => '/img/landing/hiking-trail.webp',
                'category' => 'fitness',
                'url' => 'https://example.com',
                'matched_interest_ids' => [],
                'score_manual' => 0,
            ]),
            new Event([
                'title' => 'Sound Bath — The Hoxton',
                'venue_name' => 'The Hoxton',
                'city' => 'Shoreditch, E1',
                'starts_at' => $threeWeeksOutSun->copy()->setTime(11, 0),
                'image_url' => '/img/landing/sunrise-yoga.webp',
                'category' => 'wellness',
                'url' => 'https://example.com',
                'matched_interest_ids' => [],
                'score_manual' => 0,
            ]),
        ];

        $toMatches = function (array $events, float $baseDist): array {
            return array_map(fn (Event $event, int $i) => [
                'event' => $event,
                'score' => 85.0 - ($i * 2),
                'distance_miles' => round($baseDist + ($i * 0.7), 1),
                'matched_interest_ids' => [],
                'display_interest_id' => null,
                'match_type' => 'direct',
            ], $events, array_keys($events));
        };

        $matches = [
            'weekend' => $toMatches($weekendEvents, 1.2),
            'week' => $toMatches($weekEvents, 2.1),
            'coming_soon' => $toMatches($comingSoonEvents, 4.5),
        ];

        return new WeeklyNewsletterMail(
            user: $fakeUser,
            matches: $matches,
            newsletterContext: [
                'day_type' => 'normal',
                'bucket_labels' => [
                    'weekend' => 'THIS WEEKEND',
                    'week' => 'THIS WEEK',
                    'coming_soon' => 'COMING SOON',
                ],
            ],
            seasonalPicks: [],
        );
    }
}
