<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Your weekly events near {{ $user->postcode }}</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f8f4f1; }
        img { border: 0; display: block; }
        a { color: #C4623A; text-decoration: none; }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#f8f4f1; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">

    @php
        $allMatches = collect($matches)->flatten(1);
        $totalCount = $allMatches->count();
        $bucketCount = collect($matches)->filter(fn($b) => count($b) > 0)->count();

        // ── Timing-aware display values ─────────────────────────────────────
        $dayType   = $newsletterContext['day_type'] ?? 'normal';
        $introLine = $newsletterContext['intro_line'] ?? "Here's what's happening near you";

        $bucketLabels = $newsletterContext['bucket_labels'] ?? [
            'weekend'     => 'THIS WEEKEND',
            'week'        => 'THIS WEEK',
            'coming_soon' => 'COMING SOON',
        ];

        $emojiMap = [
            'concerts'          => '&#127925;',
            'sports'            => '&#9917;',
            'comedy'            => '&#128514;',
            'food-and-drink'    => '&#127373;',
            'tech'              => '&#128187;',
            'family-days-out'   => '&#128106;',
            'markets'           => '&#128717;',
            'wellness'          => '&#129510;',
            'hiking'            => '&#127807;',
            'theatre'           => '&#127917;',
            'festivals'         => '&#127914;',
            'farming-and-rural' => '&#127806;',
            'arts-and-culture'  => '&#127912;',
        ];

        $allMatchedIds = $allMatches
            ->flatMap(function ($match) {
                $interestIds = $match['event']->matched_interest_ids ?? [];
                if (($match['display_interest_id'] ?? null) !== null) {
                    $interestIds[] = $match['display_interest_id'];
                }
                return $interestIds;
            })
            ->unique()
            ->values()
            ->all();

        $interestNames = $allMatchedIds
            ? \App\Models\Interest::query()->whereIn('id', $allMatchedIds)->pluck('name', 'id')
            : collect();
    @endphp

    {{-- Preview text (inbox snippet before email is opened) --}}
    <span style="display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; color:#f8f4f1;">
        {{ $introLine }} &mdash; {{ $totalCount }} {{ Str::plural('event', $totalCount) }} near {{ $user->postcode }}@if ($totalCount > 0), including {{ $allMatches->first()['event']->title }}.@endif
    </span>

    {{-- Outer wrapper --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f8f4f1;">
        <tr>
            <td align="center" style="padding:24px 16px;">

                {{-- Inner 600px container --}}
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px; width:100%;">

                    {{-- ── HEADER ── --}}
                    <tr>
                        <td style="background-color:#ffffff; border-bottom:1px solid #f8f4f1; border-radius:16px 16px 0 0; padding:20px 24px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="vertical-align:middle;">
                                                    <div style="width:32px; height:32px; background-color:#F5EAE3; border:1px solid #E8D5C8; border-radius:10px; text-align:center;">
                                                        <img src="{{ url('/images/logo-icon-email.png') }}" alt="" width="18" height="18" style="display:inline-block; margin-top:7px; width:18px; height:18px;" onerror="this.style.display='none'">
                                                    </div>
                                                </td>
                                                <td style="vertical-align:middle; padding-left:10px;">
                                                    <span style="font-family:'Poppins','Segoe UI',Arial,sans-serif; font-weight:700; font-size:18px; color:#1C1109; letter-spacing:-0.3px;">Nearby</span>
                                                    <span style="font-family:'Poppins','Segoe UI',Arial,sans-serif; font-weight:700; font-size:18px; color:#C4623A; letter-spacing:-0.3px;">Weekly</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td align="right" style="vertical-align:middle;">
                                        <span style="font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#9C6B54;">Week of {{ now()->format('j F') }}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ── HERO ── --}}
                    <tr>
                        <td style="background-color:#ffffff; padding:32px 24px 24px 24px;">
                            <p style="margin:0 0 10px 0; font-family:'Poppins','Segoe UI',Arial,sans-serif; font-weight:600; font-size:11px; color:#C4623A; letter-spacing:0.12em; text-transform:uppercase;">
                                Your Weekly Picks
                            </p>
                            {{-- Context-aware intro line --}}
                            <p style="margin:0 0 6px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#9C6B54; line-height:1.5;">
                                {{ $introLine }}
                            </p>
                            <h1 style="margin:0; font-family:'Poppins','Segoe UI',Arial,sans-serif; font-weight:700; font-size:26px; line-height:1.25; color:#1C1109;">
                                @if ($dayType === 'saturday')
                                    Here&rsquo;s what&rsquo;s still on near {{ $user->postcode }}
                                @elseif ($dayType === 'sunday')
                                    Plan your week near {{ $user->postcode }}
                                @else
                                    Here&rsquo;s what&rsquo;s on near {{ $user->postcode }} this week
                                @endif
                            </h1>
                            <div style="margin:14px 0 16px 0; width:40px; height:2px; background-color:#C4623A; font-size:0; line-height:0;">&nbsp;</div>
                            <p style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:1.6; color:#6B4535;">
                                {{ $totalCount }} {{ Str::plural('event', $totalCount) }} across {{ $bucketCount }} time {{ Str::plural('window', $bucketCount) }}, curated just for you.
                            </p>
                        </td>
                    </tr>

                    {{-- ── DIVIDER ── --}}
                    <tr>
                        <td style="background-color:#ffffff; padding:0 24px;">
                            <div style="height:1px; background-color:#f8f4f1; font-size:0; line-height:0;">&nbsp;</div>
                        </td>
                    </tr>

                    {{-- ── BUCKETED EVENT CARDS ── --}}
                    @if (empty($matches))
                        <tr>
                            <td style="background-color:#ffffff; padding:20px 24px;">
                                <p style="margin:0 0 20px 0; font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:1.6; color:#6B4535;">
                                    We&rsquo;re still gathering the best events for your area. Your next weekly picks will improve as more data lands.
                                </p>
                            </td>
                        </tr>
                    @else
                    @foreach ($matches as $bucketKey => $bucketEvents)
                        @if (count($bucketEvents) > 0)

                            {{-- Bucket section header --}}
                            <tr>
                                <td style="background-color:#ffffff; padding:24px 24px 12px 24px;">
                                    <p style="margin:0 0 10px 0; font-family:'Poppins','Segoe UI',Arial,sans-serif; font-weight:600; font-size:11px; color:#C4623A; letter-spacing:0.12em; text-transform:uppercase;">
                                        {{ $bucketLabels[$bucketKey] ?? strtoupper(str_replace('_', ' ', $bucketKey)) }}
                                    </p>
                                    <div style="height:1px; background-color:#f8f4f1; font-size:0; line-height:0;">&nbsp;</div>
                                </td>
                            </tr>

                            {{-- Cards for this bucket — 2-column grid --}}
                            <tr>
                                <td style="background-color:#ffffff; padding:0 24px 12px 24px;">
                                    @php $rows = array_chunk(array_slice($bucketEvents, 0, 4), 2); @endphp
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                        @foreach ($rows as $row)
                                        <tr>
                                            @foreach ($row as $match)
                                            @php
                                                $event    = $match['event'];
                                                $category = $event->category ?? '';
                                                $emoji    = $emojiMap[$category] ?? '&#128197;';
                                                $label    = $category ? strtoupper(str_replace('-', ' ', $category)) : 'EVENT';
                                                $isSuggestion = ($match['match_type'] ?? 'direct') === 'suggestion';

                                                $matchedInterestName = ($match['display_interest_id'] ?? null) !== null
                                                    ? ($interestNames[$match['display_interest_id']] ?? null)
                                                    : null;
                                            @endphp
                                            {{-- Card cell (272px = (552 - 8px gap) / 2) --}}
                                            <td width="272" valign="top" style="width:272px; padding-bottom:8px; padding-right:{{ $loop->first ? '8px' : '0' }};">
                                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e2e8f0; border-radius:16px; overflow:hidden; background-color:#ffffff;">
                                                    <tr>
                                                        <td style="padding:0;">
                                                            @if ($event->image_url)
                                                                <img
                                                                    src="{{ $event->image_url }}"
                                                                    alt="{{ $event->title }}"
                                                                    width="270"
                                                                    height="140"
                                                                    style="width:100%; height:140px; object-fit:cover; display:block; border-radius:16px 16px 0 0;"
                                                                >
                                                            @else
                                                                <div style="height:100px; background-color:#FDF7F4; text-align:center; font-size:28px; line-height:100px; color:#C4623A; border-radius:16px 16px 0 0;">
                                                                    {!! $emoji !!}
                                                                </div>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding:14px;">

                                                            {{-- Category badge --}}
                                                            <span style="display:inline-block; padding:3px 8px; background-color:#F5EAE3; color:#C4623A; border-radius:20px; font-family:'Poppins','Segoe UI',Arial,sans-serif; font-weight:600; font-size:10px; letter-spacing:0.08em; text-transform:uppercase;">
                                                                {!! $emoji !!} {{ $label }}
                                                            </span>

                                                            {{-- Suggestion / match indicator --}}
                                                            @if ($isSuggestion)
                                                                {{-- "We thought you'd like" pill for non-selected interest suggestions --}}
                                                                <span style="display:inline-block; margin-top:6px; padding:3px 8px; background-color:#FFF7ED; border:1px solid #FED7AA; color:#C2610C; border-radius:20px; font-family:'Poppins','Segoe UI',Arial,sans-serif; font-weight:600; font-size:10px; letter-spacing:0.05em;">
                                                                    &#10024; We thought you&rsquo;d like
                                                                    @if ($matchedInterestName)
                                                                        &middot; {{ $matchedInterestName }}
                                                                    @endif
                                                                </span>
                                                            @elseif ($matchedInterestName)
                                                                <p style="margin:6px 0 0 0; font-family:Arial,Helvetica,sans-serif; font-size:11px; color:#9C6B54; line-height:1.4;">
                                                                    Matched: {{ $matchedInterestName }}
                                                                </p>
                                                            @endif

                                                            {{-- Title --}}
                                                            <p style="margin:6px 0 8px 0; font-family:'Poppins','Segoe UI',Arial,sans-serif; font-weight:700; font-size:14px; line-height:1.3; color:#1C1109;">
                                                                {{ $event->title }}
                                                            </p>

                                                            {{-- Venue + date --}}
                                                            @php $venueParts = array_filter([$event->venue_name, $event->city]); @endphp
                                                            @if ($venueParts)
                                                                <p style="margin:0 0 3px 0; font-family:Arial,Helvetica,sans-serif; font-size:11px; color:#6B4535; line-height:1.4;">
                                                                    &#128205; {{ implode(', ', $venueParts) }}
                                                                </p>
                                                            @endif
                                                            <p style="margin:0 0 8px 0; font-family:Arial,Helvetica,sans-serif; font-size:11px; color:#6B4535; line-height:1.4;">
                                                                &#128197; {{ $event->starts_at->format('j M, g:ia') }}
                                                            </p>

                                                            {{-- Distance chip --}}
                                                            <span style="display:inline-block; padding:3px 8px; background-color:#f8f4f1; border-radius:20px; font-family:Arial,Helvetica,sans-serif; font-size:11px; color:#475569;">
                                                                {{ round($match['distance_miles']) }} miles away
                                                            </span>

                                                            {{-- CTA button --}}
                                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:12px;">
                                                                <tr>
                                                                    <td>
                                                                        <a href="{{ url('/events/' . $event->id . '/go') }}" style="display:block; padding:10px; background-color:#C4623A; color:#ffffff; text-align:center; font-family:'Poppins','Segoe UI',Arial,sans-serif; font-weight:600; font-size:13px; border-radius:10px; text-decoration:none;">
                                                                            Get tickets &rarr;
                                                                        </a>
                                                                    </td>
                                                                </tr>
                                                            </table>

                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                            @endforeach

                                            {{-- Pad empty cell if odd number in row --}}
                                            @if (count($row) === 1)
                                            <td width="272" style="width:272px;">&nbsp;</td>
                                            @endif
                                        </tr>
                                        @endforeach
                                    </table>
                                </td>
                            </tr>

                        @endif
                    @endforeach
                    @endif

                    {{-- ── SEASONAL PICKS ── --}}
                    @if(!empty($newsletterContext['seasonal_label']) && !empty($seasonalPicks))

                        {{-- Section header --}}
                        <tr>
                            <td style="background-color:#ffffff; padding:24px 24px 12px 24px;">
                                <p style="margin:0 0 10px 0; font-family:'Poppins','Segoe UI',Arial,sans-serif; font-weight:600; font-size:11px; color:#C4623A; letter-spacing:0.12em; text-transform:uppercase;">
                                    {{ $newsletterContext['seasonal_label'] }}
                                </p>
                                <div style="height:1px; background-color:#f8f4f1; font-size:0; line-height:0;">&nbsp;</div>
                            </td>
                        </tr>

                        {{-- Seasonal cards — same 2-column layout as main buckets --}}
                        <tr>
                            <td style="background-color:#ffffff; padding:0 24px 12px 24px;">
                                @php $seasonalRows = array_chunk($seasonalPicks, 2); @endphp
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                    @foreach ($seasonalRows as $row)
                                    <tr>
                                        @foreach ($row as $pick)
                                        @php
                                            $event    = $pick['event'];
                                            $category = $event->category ?? '';
                                            $emoji    = $emojiMap[$category] ?? '&#128197;';
                                            $label    = $category ? strtoupper(str_replace('-', ' ', $category)) : 'EVENT';
                                        @endphp
                                        <td width="272" valign="top" style="width:272px; padding-bottom:8px; padding-right:{{ $loop->first ? '8px' : '0' }};">
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e2e8f0; border-radius:16px; overflow:hidden; background-color:#ffffff;">
                                                <tr>
                                                    <td style="padding:0;">
                                                        @if ($event->image_url)
                                                            <img
                                                                src="{{ $event->image_url }}"
                                                                alt="{{ $event->title }}"
                                                                width="270"
                                                                height="140"
                                                                style="width:100%; height:140px; object-fit:cover; display:block; border-radius:16px 16px 0 0;"
                                                            >
                                                        @else
                                                            <div style="height:100px; background-color:#FDF7F4; text-align:center; font-size:28px; line-height:100px; color:#C4623A; border-radius:16px 16px 0 0;">
                                                                {!! $emoji !!}
                                                            </div>
                                                        @endif
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="padding:14px;">

                                                        {{-- Category badge --}}
                                                        <span style="display:inline-block; padding:3px 8px; background-color:#F5EAE3; color:#C4623A; border-radius:20px; font-family:'Poppins','Segoe UI',Arial,sans-serif; font-weight:600; font-size:10px; letter-spacing:0.08em; text-transform:uppercase;">
                                                            {!! $emoji !!} {{ $label }}
                                                        </span>

                                                        {{-- Title --}}
                                                        <p style="margin:6px 0 8px 0; font-family:'Poppins','Segoe UI',Arial,sans-serif; font-weight:700; font-size:14px; line-height:1.3; color:#1C1109;">
                                                            {{ $event->title }}
                                                        </p>

                                                        {{-- Venue + date --}}
                                                        @php $venueParts = array_filter([$event->venue_name, $event->city]); @endphp
                                                        @if ($venueParts)
                                                            <p style="margin:0 0 3px 0; font-family:Arial,Helvetica,sans-serif; font-size:11px; color:#6B4535; line-height:1.4;">
                                                                &#128205; {{ implode(', ', $venueParts) }}
                                                            </p>
                                                        @endif
                                                        <p style="margin:0 0 8px 0; font-family:Arial,Helvetica,sans-serif; font-size:11px; color:#6B4535; line-height:1.4;">
                                                            &#128197; {{ $event->starts_at->format('j M, g:ia') }}
                                                        </p>

                                                        {{-- Distance chip --}}
                                                        <span style="display:inline-block; padding:3px 8px; background-color:#f8f4f1; border-radius:20px; font-family:Arial,Helvetica,sans-serif; font-size:11px; color:#475569;">
                                                            {{ round($pick['distance_miles']) }} miles away
                                                        </span>

                                                        {{-- CTA button --}}
                                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:12px;">
                                                            <tr>
                                                                <td>
                                                                    <a href="{{ url('/events/' . $event->id . '/go') }}" style="display:block; padding:10px; background-color:#C4623A; color:#ffffff; text-align:center; font-family:'Poppins','Segoe UI',Arial,sans-serif; font-weight:600; font-size:13px; border-radius:10px; text-decoration:none;">
                                                                        Get tickets &rarr;
                                                                    </a>
                                                                </td>
                                                            </tr>
                                                        </table>

                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                        @endforeach

                                        {{-- Pad empty cell if odd number in row --}}
                                        @if (count($row) === 1)
                                        <td width="272" style="width:272px;">&nbsp;</td>
                                        @endif
                                    </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>

                    @endif

                    {{-- ── DIVIDER ── --}}
                    <tr>
                        <td style="background-color:#ffffff; padding:0 24px;">
                            <div style="height:1px; background-color:#f8f4f1; font-size:0; line-height:0;">&nbsp;</div>
                        </td>
                    </tr>

                    {{-- ── FOOTER ── --}}
                    <tr>
                        <td style="background-color:#f8fafc; border-top:1px solid #e2e8f0; border-radius:0 0 16px 16px; padding:32px 24px; text-align:center;">

                            {{-- Footer logo --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto 12px auto;">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        <div style="width:22px; height:22px; background-color:#F5EAE3; border:1px solid #E8D5C8; border-radius:6px; text-align:center;">
                                            <img src="{{ url('/images/logo-icon-email.png') }}" alt="" width="12" height="12" style="display:inline-block; margin-top:5px; width:12px; height:12px;" onerror="this.style.display='none'">
                                        </div>
                                    </td>
                                    <td style="vertical-align:middle; padding-left:8px;">
                                        <span style="font-family:'Poppins','Segoe UI',Arial,sans-serif; font-weight:700; font-size:14px; color:#1C1109;">Nearby</span>
                                        <span style="font-family:'Poppins','Segoe UI',Arial,sans-serif; font-weight:700; font-size:14px; color:#C4623A;">Weekly</span>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 16px 0; font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#9C6B54; line-height:1.5;">
                                Your weekly picks, curated by interest and location.
                            </p>

                            <p style="margin:0 0 20px 0; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.5;">
                                <a href="{{ url('/preferences') }}" style="color:#C4623A; text-decoration:none;">Update my preferences</a>
                                &nbsp;&nbsp;&middot;&nbsp;&nbsp;
                                <a href="{{ $unsubscribeUrl }}" style="color:#C4623A; text-decoration:none;">Unsubscribe</a>
                            </p>

                            <p style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#9C6B54; line-height:1.6;">
                                You&rsquo;re receiving this because you signed up at nearbyweekly.co.uk.<br>We&rsquo;ll never share your email.
                            </p>

                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>
