<?php

namespace App\Models;

use Database\Factories\InterestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Interest extends Model
{
    /** @use HasFactory<InterestFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'sort_order',
    ];

    public static function grouped(): array
    {
        $emojiMap = [
            'music' => '🎵',
            'arts-and-entertainment' => '🎭',
            'food-and-drink' => '🍽️',
            'health-and-fitness' => '💪',
            'outdoors-and-nature' => '🌿',
            'family' => '👨‍👩‍👧',
            'tech-and-professional' => '💼',
        ];

        return self::query()
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->orderBy('name')])
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug'])
            ->map(fn (self $parent) => [
                'id' => $parent->id,
                'name' => $parent->name,
                'slug' => $parent->slug,
                'emoji' => $emojiMap[$parent->slug] ?? '📅',
                'children' => $parent->children->map(fn (self $child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'slug' => $child->slug,
                ])->all(),
            ])
            ->all();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Interest::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Interest::class, 'parent_id');
    }

    public function isParent(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Get the users that belong to the interest.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_interests')->withTimestamps();
    }
}
