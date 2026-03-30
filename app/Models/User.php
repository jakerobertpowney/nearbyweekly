<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable([
    'name',
    'email',
    'password',
    'postcode',
    'latitude',
    'longitude',
    'radius_miles',
    'newsletter_enabled',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'postcode',
        'latitude',
        'longitude',
        'radius_miles',
        'newsletter_enabled',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'newsletter_enabled' => 'bool',
        ];
    }

    /**
     * Get the pending login links for the user.
     */
    public function loginLinks(): HasMany
    {
        return $this->hasMany(LoginLink::class);
    }

    /**
     * Get the interests selected by the user.
     */
    public function interests(): BelongsToMany
    {
        return $this->belongsToMany(Interest::class, 'user_interests')->withTimestamps();
    }

    /**
     * Get the newsletter items for the user.
     */
    public function newsletterItems(): HasMany
    {
        return $this->hasMany(NewsletterItem::class);
    }
}
