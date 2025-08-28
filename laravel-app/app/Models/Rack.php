<?php

/**
 * @OA\Schema(
 *     schema="Rack",
 *     type="object",
 *     title="Rack",
 *     description="Ableton Live rack model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="uuid", type="string", format="uuid"),
 *     @OA\Property(property="title", type="string", example="Epic Bass Rack"),
 *     @OA\Property(property="description", type="string", example="A powerful bass rack with multiple layers"),
 *     @OA\Property(property="slug", type="string", example="epic-bass-rack"),
 *     @OA\Property(property="rack_type", type="string", enum={"instrument", "audio_effect", "midi_effect"}),
 *     @OA\Property(property="category", type="string", example="bass"),
 *     @OA\Property(property="device_count", type="integer", example=5),
 *     @OA\Property(property="chain_count", type="integer", example=3),
 *     @OA\Property(property="ableton_version", type="string", example="11.3.4"),
 *     @OA\Property(property="average_rating", type="number", format="float", example=4.5),
 *     @OA\Property(property="ratings_count", type="integer", example=25),
 *     @OA\Property(property="downloads_count", type="integer", example=150),
 *     @OA\Property(property="views_count", type="integer", example=500),
 *     @OA\Property(property="comments_count", type="integer", example=12),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="published_at", type="string", format="date-time"),
 *     @OA\Property(
 *         property="user",
 *         ref="#/components/schemas/User"
 *     ),
 *     @OA\Property(
 *         property="tags",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/Tag")
 *     )
 * )
 */


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\MarkdownService;

class Rack extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'title',
        'description',
        'how_to_article',
        'how_to_updated_at',
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
        'version',
        'last_auto_save',
        'last_auto_save_session',
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
        'how_to_updated_at' => 'datetime',
        'last_auto_save' => 'datetime',
        'is_public' => 'boolean',
        'is_featured' => 'boolean',
        'average_rating' => 'decimal:2',
        'version' => 'integer',
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
    /**
     * Get the display name for the category
     */
    public function getCategoryDisplayAttribute()
    {
        // Map numeric categories to display names
        $categoryMap = [
            "1" => "Lead",
            "2" => "Bass",
            "3" => "Drums",
            "4" => "Pad",
            "5" => "Arp",
            "6" => "FX",
            "7" => "Texture",
            "8" => "Vocal",
            "dynamics" => "Dynamics",
            "time-based" => "Time Based",
            "modulation" => "Modulation",
            "spectral" => "Spectral",
            "filters" => "Filters",
            "creative-effects" => "Creative Effects",
            "utility" => "Utility",
            "mixing" => "Mixing",
            "distortion" => "Distortion"
        ];
        
        return $categoryMap[$this->category] ?? $this->category;
    }
    

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

    /**
     * Get HTML version of how-to article from markdown
     */
    public function getHtmlHowToAttribute(): ?string
    {
        if (empty($this->how_to_article)) {
            return null;
        }

        return app(MarkdownService::class)->parseToHtml($this->how_to_article);
    }

    /**
     * Get truncated preview of how-to article (plain text)
     */
    public function getHowToPreviewAttribute(int $length = 200): ?string
    {
        if (empty($this->how_to_article)) {
            return null;
        }

        // Strip markdown formatting and get plain text
        $plainText = app(MarkdownService::class)->stripMarkdown($this->how_to_article);
        
        return Str::limit($plainText, $length);
    }

    /**
     * Check if rack has a how-to article
     */
    public function hasHowToArticle(): bool
    {
        return !empty($this->how_to_article);
    }

    /**
     * Scope for racks with how-to articles
     */
    public function scopeWithHowTo($query)
    {
        return $query->whereNotNull('how_to_article')
                    ->where('how_to_article', '!=', '');
    }

    /**
     * Update how-to article timestamp
     */
    public function touchHowTo(): void
    {
        $this->how_to_updated_at = now();
        $this->save();
    }
    
    /**
     * Get reading time estimate for how-to article
     */
    public function getReadingTimeHowToAttribute()
    {
        if (!$this->how_to_article) {
            return 0;
        }
        
        $words = str_word_count(strip_tags($this->how_to_article));
        $minutes = ceil($words / 200); // Average reading speed
        return $minutes;
    }
}