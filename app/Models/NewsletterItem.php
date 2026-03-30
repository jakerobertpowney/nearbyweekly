<?php

namespace App\Models;

use Database\Factories\NewsletterItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterItem extends Model
{
    /** @use HasFactory<NewsletterItemFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'newsletter_run_id',
        'user_id',
        'event_id',
        'ranking_score',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ranking_score' => 'decimal:2',
        ];
    }

    /**
     * Get the newsletter run that owns the item.
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(NewsletterRun::class, 'newsletter_run_id');
    }

    /**
     * Get the user that owns the item.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the event that owns the item.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
