<?php

namespace App\Models;

use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\EventClick;

class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'source',
        'external_id',
        'title',
        'slug',
        'description',
        'category',
        'matched_interest_ids',
        'venue_name',
        'address_line',
        'city',
        'postcode',
        'latitude',
        'longitude',
        'starts_at',
        'ends_at',
        'url',
        'url_status',
        'url_checked_at',
        'image_url',
        'score_manual',
        'popularity_score',
        'tags',
        'raw_payload',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'url_checked_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'matched_interest_ids' => 'array',
            'popularity_score' => 'float',
            'tags' => 'array',
            'raw_payload' => 'array',
        ];
    }

    /**
     * Get the newsletter items for the event.
     */
    public function newsletterItems(): HasMany
    {
        return $this->hasMany(NewsletterItem::class);
    }

    /**
     * Get the click-through records for the event.
     */
    public function clicks(): HasMany
    {
        return $this->hasMany(EventClick::class);
    }
}
