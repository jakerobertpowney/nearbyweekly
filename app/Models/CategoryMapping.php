<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryMapping extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'interest_id',
        'source',
        'external_category',
        'ai_generated',
    ];

    /**
     * Get the interest for this mapping.
     */
    public function interest(): BelongsTo
    {
        return $this->belongsTo(Interest::class);
    }
}
