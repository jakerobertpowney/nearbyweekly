<?php

namespace App\Models;

use Database\Factories\NewsletterRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsletterRun extends Model
{
    /** @use HasFactory<NewsletterRunFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'scheduled_for',
        'sent_at',
        'status',
        'fallback_level',
        'bucket_summary',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
            'bucket_summary' => 'array',
        ];
    }

    /**
     * Get the items created during the run.
     */
    public function items(): HasMany
    {
        return $this->hasMany(NewsletterItem::class);
    }
}
