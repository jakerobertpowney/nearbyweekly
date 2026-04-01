<?php

namespace App\Services\Events;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class SeasonalCalendar
{
    /**
     * Return all seasons that are currently active for the given date.
     *
     * Each season is a plain array:
     *   ['slug' => string, 'label' => string, 'emoji' => string, 'tags' => list<string>]
     *
     * Multiple seasons can be active simultaneously (e.g. Christmas + New Year).
     *
     * @return list<array{slug: string, label: string, emoji: string, tags: list<string>}>
     */
    public function activeSeasons(CarbonInterface $date): array
    {
        $active = [];

        foreach ($this->seasons($date->year) as $season) {
            if ($date->between($season['start'], $season['end'])) {
                unset($season['start'], $season['end']);
                $active[] = $season;
            }
        }

        // New Year window can straddle a year boundary (Dec 28 → Jan 3).
        // If we're in early January, also check the previous year's definitions.
        if ($date->month === 1 && $date->day <= 3) {
            foreach ($this->seasons($date->year - 1) as $season) {
                if ($season['slug'] === 'new-year' && $date->between($season['start'], $season['end'])) {
                    unset($season['start'], $season['end']);
                    // Avoid duplicating if already added above
                    if (! collect($active)->contains('slug', 'new-year')) {
                        $active[] = $season;
                    }
                }
            }
        }

        return $active;
    }

    /**
     * Build the full list of season definitions for a given year.
     * Each entry includes 'start' and 'end' Carbon instances used for window checks.
     */
    private function seasons(int $year): array
    {
        [$goodFriday, $easterMonday] = $this->easterWindow($year);
        $mayBankHoliday              = $this->firstMondayOf($year, 5);
        $summerBankHoliday           = $this->lastMondayOf($year, 8);

        return [
            [
                'slug'  => 'valentines',
                'label' => "Valentine's picks near you",
                'emoji' => '💝',
                'tags'  => ['valentines', 'romantic'],
                'start' => Carbon::create($year, 2, 11)->startOfDay(),
                'end'   => Carbon::create($year, 2, 14)->endOfDay(),
            ],
            [
                'slug'  => 'easter',
                'label' => 'Easter picks near you',
                'emoji' => '🐣',
                'tags'  => ['easter', 'easter-egg-hunt', 'spring-fair'],
                'start' => $goodFriday->copy()->startOfDay(),
                'end'   => $easterMonday->copy()->endOfDay(),
            ],
            [
                'slug'  => 'may-bank-holiday',
                'label' => 'Bank holiday weekend picks',
                'emoji' => '🌸',
                'tags'  => ['bank-holiday', 'spring'],
                'start' => $mayBankHoliday->copy()->subDays(3)->startOfDay(),
                'end'   => $mayBankHoliday->copy()->addDays(3)->endOfDay(),
            ],
            [
                'slug'  => 'halloween',
                'label' => 'Halloween near you',
                'emoji' => '🎃',
                'tags'  => ['halloween', 'spooky', 'horror'],
                'start' => Carbon::create($year, 10, 25)->startOfDay(),
                'end'   => Carbon::create($year, 10, 31)->endOfDay(),
            ],
            [
                'slug'  => 'bonfire-night',
                'label' => 'Bonfire Night near you',
                'emoji' => '🎆',
                'tags'  => ['bonfire-night', 'fireworks'],
                'start' => Carbon::create($year, 11, 3)->startOfDay(),
                'end'   => Carbon::create($year, 11, 7)->endOfDay(),
            ],
            [
                'slug'  => 'christmas',
                'label' => 'Christmas events near you',
                'emoji' => '🎄',
                'tags'  => ['christmas', 'christmas-market', 'festive'],
                'start' => Carbon::create($year, 12, 1)->startOfDay(),
                'end'   => Carbon::create($year, 12, 28)->endOfDay(),
            ],
            [
                'slug'  => 'new-year',
                'label' => 'New Year events near you',
                'emoji' => '🥂',
                'tags'  => ['new-year', 'nye'],
                'start' => Carbon::create($year, 12, 28)->startOfDay(),
                'end'   => Carbon::create($year + 1, 1, 3)->endOfDay(),
            ],
            [
                'slug'  => 'summer-bank-holiday',
                'label' => 'Bank holiday weekend picks',
                'emoji' => '☀️',
                'tags'  => ['bank-holiday', 'summer'],
                'start' => $summerBankHoliday->copy()->subDays(3)->startOfDay(),
                'end'   => $summerBankHoliday->copy()->addDays(3)->endOfDay(),
            ],
        ];
    }

    /**
     * Return [Good Friday, Easter Monday] as Carbon instances for the given year.
     *
     * @return array{CarbonInterface, CarbonInterface}
     */
    private function easterWindow(int $year): array
    {
        $easterSunday = Carbon::createFromTimestamp(easter_date($year))->startOfDay();
        $goodFriday   = $easterSunday->copy()->subDays(2);
        $easterMonday = $easterSunday->copy()->addDay();

        return [$goodFriday, $easterMonday];
    }

    /** Return the first Monday of the given month/year as a Carbon instance. */
    private function firstMondayOf(int $year, int $month): Carbon
    {
        $day = Carbon::create($year, $month, 1);

        return $day->dayOfWeek === Carbon::MONDAY
            ? $day
            : $day->next(Carbon::MONDAY);
    }

    /** Return the last Monday of the given month/year as a Carbon instance. */
    private function lastMondayOf(int $year, int $month): Carbon
    {
        $day = Carbon::create($year, $month, 1)->endOfMonth()->startOfDay();

        return $day->dayOfWeek === Carbon::MONDAY
            ? $day
            : $day->previous(Carbon::MONDAY);
    }
}
