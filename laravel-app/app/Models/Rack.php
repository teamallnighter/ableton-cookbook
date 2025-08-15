<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Rack extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'title',
        'description',
        'slug',
        'file_path',
        'file_hash',
        'file_size',
        'original_filename',
        'rack_type',
        'category',
        'device_count',
        'chain_count',
        'ableton_version',
        'ableton_edition',
        'macro_controls',
        'devices',
        'chains',
        'chain_annotations',
        'version_details',
        'parsing_errors',
        'parsing_warnings',
        'preview_audio_path',
        'preview_image_path',
        'status',
        'processing_error',
        'published_at',
        'average_rating',
        'ratings_count',
        'downloads_count',
        'views_count',
        'comments_count',
        'likes_count',
        'is_public',
        'is_featured',
    ];

    protected $casts = [
        'macro_controls' => 'array',
        'devices' => 'array',
        'chains' => 'array',
        'chain_annotations' => 'array',
        'version_details' => 'array',
        'parsing_errors' => 'array',
        'parsing_warnings' => 'array',
        'published_at' => 'datetime',
        'is_public' => 'boolean',
        'is_featured' => 'boolean',
        'average_rating' => 'decimal:2',
    ];

    /**
     * Get the user that owns the rack
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tags for the rack
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'rack_tags');
    }

    /**
     * Get the ratings for the rack
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(RackRating::class);
    }

    /**
     * Get the comments for the rack
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Get the downloads for the rack
     */
    public function downloads(): HasMany
    {
        return $this->hasMany(RackDownload::class);
    }

    /**
     * Get the favorites for the rack
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(RackFavorite::class);
    }

    /**
     * Get the reports for the rack
     */
    public function reports(): HasMany
    {
        return $this->hasMany(RackReport::class);
    }

    /**
     * Get the collections that include this rack
     */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'collection_racks')
            ->withPivot('position', 'notes')
            ->withTimestamps();
    }

    /**
     * Get activity feed entries for this rack
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(UserActivityFeed::class, 'subject');
    }

    /**
     * Scope for published racks
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'approved')
            ->where('is_public', true)
            ->whereNotNull('published_at');
    }

    /**
     * Scope for featured racks
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Check if user has rated this rack
     */
    public function hasBeenRatedBy(User $user): bool
    {
        return $this->ratings()->where('user_id', $user->id)->exists();
    }

    /**
     * Get user's rating for this rack
     */
    public function getUserRating(User $user): ?RackRating
    {
        return $this->ratings()->where('user_id', $user->id)->first();
    }

    /**
     * Update average rating
     */
    public function updateAverageRating(): void
    {
        $stats = $this->ratings()
            ->selectRaw('AVG(rating) as average, COUNT(*) as count')
            ->first();

        $this->update([
            'average_rating' => $stats->average ?? 0,
            'ratings_count' => $stats->count ?? 0,
        ]);
    }

    /**
     * Increment download count
     */
    public function recordDownload(?User $user = null, string $ipAddress = null): void
    {
        $this->downloads()->create([
            'user_id' => $user?->id,
            'ip_address' => $ipAddress ?? request()->ip(),
            'user_agent' => request()->userAgent(),
            'download_token' => Str::random(64),
            'downloaded_at' => now(),
        ]);

        $this->increment('downloads_count');
    }

    /**
     * Get download URL with temporary signed URL
     */
    public function getDownloadUrl(): string
    {
        return Storage::disk('private')->temporaryUrl(
            $this->file_path,
            now()->addMinutes(5)
        );
    }
}