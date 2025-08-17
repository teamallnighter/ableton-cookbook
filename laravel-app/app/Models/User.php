<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Overtrue\LaravelFollow\Traits\Followable;
use Overtrue\LaravelFollow\Traits\Follower;
use Spatie\Permission\Traits\HasRoles;

use App\Notifications\CustomVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use Followable;
    use Follower;
    use HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'bio',
        'location',
        'website',
        'soundcloud_url',
        'bandcamp_url',
        'spotify_url',
        'youtube_url',
        'instagram_url',
        'twitter_url',
        'notification_preferences',
        'email_notifications_enabled',
        'last_notification_read_at',
        'email_consent',
        'email_consent_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
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
            'notification_preferences' => 'array',
            'last_notification_read_at' => 'datetime',
            'email_consent_at' => 'datetime',
        ];
    }

    /**
     * Get the racks uploaded by the user
     */
    public function racks(): HasMany
    {
        return $this->hasMany(Rack::class);
    }

    /**
     * Get the user's rack ratings
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(RackRating::class);
    }

    /**
     * Get the user's comments
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Get the user's collections
     */
    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    /**
     * Get the user's activity feed
     */
    public function activities(): HasMany
    {
        return $this->hasMany(UserActivityFeed::class);
    }

    /**
     * Get the user's download history
     */
    public function downloads(): HasMany
    {
        return $this->hasMany(RackDownload::class);
    }

    /**
     * Get the user's favorite racks
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(RackFavorite::class);
    }

    /**
     * Get the racks this user has favorited through the pivot table
     */
    public function favoriteRacks()
    {
        return $this->hasManyThrough(
            Rack::class,
            RackFavorite::class,
            'user_id', // Foreign key on RackFavorite table
            'id',      // Foreign key on Rack table
            'id',      // Local key on User table
            'rack_id'  // Local key on RackFavorite table
        );
    }

    /**
     * Get user statistics
     */
    /**
     * Send the email verification notification.
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new CustomVerifyEmail);
    }


    public function getStatistics(): array
    {
        return [
            'racks_count' => $this->racks()->where('status', 'approved')->count(),
            'followers_count' => $this->followers()->count(),
            'following_count' => $this->followings()->count(),
            'total_downloads' => $this->racks()->sum('downloads_count'),
            'average_rating' => $this->racks()
                ->where('status', 'approved')
                ->where('ratings_count', '>', 0)
                ->avg('average_rating'),
        ];
    }
}
