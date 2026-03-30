# Scope: Interest Group Sort Order

## Problem

Parent interests are currently ordered alphabetically (`->orderBy('name')`) in both `OnboardingController` and `PreferenceController`. This surfaces Arts & Entertainment first and Tech & Professional last for no reason other than the alphabet. Leading with broad, high-recognition categories improves first impressions on the onboarding flow.

The desired order, based on global event popularity and first-screen impact:

| Position | Group |
|---|---|
| 1 | Music |
| 2 | Food & Drink |
| 3 | Arts & Entertainment |
| 4 | Health & Fitness |
| 5 | Family |
| 6 | Outdoors & Nature |
| 7 | Tech & Professional |

---

## Approach — `sort_order` column

A `sort_order` integer column on `interests` is the right approach. The alternatives are worse: ordering by `id` is fragile (IDs shift if records are deleted and re-seeded), and a hardcoded `orderByRaw` CASE statement in the query is unmaintainable as the group list grows. A dedicated column is explicit, re-orderable at any time without a migration, and readable.

---

## Changes

### 1. Migration — add `sort_order` to `interests`

```php
$table->unsignedSmallInteger('sort_order')->default(0)->after('slug');
```

No index needed — there are at most a handful of parent rows and the column is only used for ordering a small result set.

### 2. `InterestSeeder` — set `sort_order` on parents

Add `sort_order` to each parent entry in the `$parents` array:

```php
$parents = [
    ['name' => 'Music',                'slug' => 'music',                  'sort_order' => 1],
    ['name' => 'Food & Drink',         'slug' => 'food-and-drink',         'sort_order' => 2],
    ['name' => 'Arts & Entertainment', 'slug' => 'arts-and-entertainment', 'sort_order' => 3],
    ['name' => 'Health & Fitness',     'slug' => 'health-and-fitness',     'sort_order' => 4],
    ['name' => 'Family',               'slug' => 'family',                 'sort_order' => 5],
    ['name' => 'Outdoors & Nature',    'slug' => 'outdoors-and-nature',    'sort_order' => 6],
    ['name' => 'Tech & Professional',  'slug' => 'tech-and-professional',  'sort_order' => 7],
];
```

Include `sort_order` in the `updateOrCreate` attributes (second argument) so re-running the seeder keeps order in sync.

Children don't need `sort_order` — they're already ordered by `name` within each group card, which is fine.

### 3. Extract `groupedInterests()` to the `Interest` model

`groupedInterests()` is duplicated verbatim in both `OnboardingController` and `PreferenceController`. Both copies use `->orderBy('name')`. Rather than fixing both separately now and risking them diverging again later, extract it to a static method on `Interest`:

```php
// app/Models/Interest.php
public static function grouped(): array
{
    $emojiMap = [
        'music'                  => '🎵',
        'arts-and-entertainment' => '🎭',
        'food-and-drink'         => '🍽️',
        'health-and-fitness'     => '💪',
        'outdoors-and-nature'    => '🌿',
        'family'                 => '👨‍👩‍👧',
        'tech-and-professional'  => '💼',
    ];

    return self::query()
        ->whereNull('parent_id')
        ->with(['children' => fn ($q) => $q->orderBy('name')])
        ->orderBy('sort_order')
        ->get(['id', 'name', 'slug'])
        ->map(fn (self $parent) => [
            'id'       => $parent->id,
            'name'     => $parent->name,
            'slug'     => $parent->slug,
            'emoji'    => $emojiMap[$parent->slug] ?? '📅',
            'children' => $parent->children->map(fn (self $child) => [
                'id'   => $child->id,
                'name' => $child->name,
                'slug' => $child->slug,
            ])->all(),
        ])
        ->all();
}
```

Then in both controllers, replace the private `groupedInterests()` method body with a call to `Interest::grouped()`, and delete the now-redundant private method from each.

---

## Files Changed

| File | Change |
|---|---|
| New migration | Add `sort_order` `unsignedSmallInteger` column to `interests` |
| `database/seeders/InterestSeeder.php` | Add `sort_order` to each parent entry |
| `app/Models/Interest.php` | Add `static grouped()` method with `orderBy('sort_order')` |
| `app/Http/Controllers/OnboardingController.php` | Replace private `groupedInterests()` with `Interest::grouped()` |
| `app/Http/Controllers/PreferenceController.php` | Replace private `groupedInterests()` with `Interest::grouped()` |
