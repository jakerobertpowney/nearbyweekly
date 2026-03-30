<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IngestionLog extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'provider',
        'status',
        'fetched',
        'created',
        'updated',
        'skipped',
        'failed',
        'rate_limit_remaining',
        'rate_limit_total',
        'rate_limit_reset_at',
        'ran_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ran_at' => 'datetime',
            'fetched' => 'integer',
            'created' => 'integer',
            'updated' => 'integer',
            'skipped' => 'integer',
            'failed' => 'integer',
            'rate_limit_remaining' => 'integer',
            'rate_limit_total' => 'integer',
            'rate_limit_reset_at' => 'datetime',
        ];
    }
}
