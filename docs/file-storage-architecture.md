# File Upload and Storage Architecture for Ableton Cookbook

## Overview

The Ableton Cookbook platform handles three main types of files:
1. **.adg files** - Ableton Device Group files (core workflow files)
2. **Audio previews** - MP3/WAV preview files for workflows
3. **User media** - Profile pictures and workflow images

## Storage Strategy

### Multi-Tier Storage Architecture

```php
<?php
// config/filesystems.php

'disks' => [
    'local' => [
        'driver' => 'local',
        'root' => storage_path('app'),
        'throw' => false,
    ],

    'public' => [
        'driver' => 'local',
        'root' => storage_path('app/public'),
        'url' => env('APP_URL').'/storage',
        'visibility' => 'public',
        'throw' => false,
    ],

    // Primary storage for .adg files (private)
    's3' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET'),
        'url' => env('AWS_URL'),
        'endpoint' => env('AWS_ENDPOINT'),
        'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        'throw' => false,
    ],

    // CDN storage for public media (audio previews, images)
    's3_public' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_PUBLIC_BUCKET'),
        'url' => env('AWS_PUBLIC_URL'),
        'endpoint' => env('AWS_ENDPOINT'),
        'visibility' => 'public',
        'throw' => false,
    ],

    // Backup storage
    's3_backup' => [
        'driver' => 's3',
        'key' => env('AWS_BACKUP_ACCESS_KEY_ID'),
        'secret' => env('AWS_BACKUP_SECRET_ACCESS_KEY'),
        'region' => env('AWS_BACKUP_REGION'),
        'bucket' => env('AWS_BACKUP_BUCKET'),
        'throw' => false,
    ],
],

'default' => env('FILESYSTEM_DISK', 'local'),

// Media Library Configuration
'media_library' => [
    'disk_name' => env('MEDIA_DISK', 's3_public'),
    'max_file_size' => 1024 * 1024 * 50, // 50MB
    'path_generator' => \App\MediaLibrary\CustomPathGenerator::class,
],
```

## File Upload Handling

### Workflow File Upload Service

```php
<?php
// app/Services/WorkflowFileService.php

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use App\Jobs\ProcessWorkflowFile;
use App\Jobs\GenerateAudioPreview;

class WorkflowFileService
{
    protected $adgDisk = 's3';
    protected $mediaDisk = 's3_public';
    protected $backupDisk = 's3_backup';

    public function processWorkflowUpload(UploadedFile $adgFile, ?UploadedFile $previewAudio = null, ?UploadedFile $previewImage = null): array
    {
        $processedFiles = [];

        // Generate unique identifiers
        $fileId = Str::uuid();
        $timestamp = now()->format('Y/m/d');

        // 1. Process ADG file
        $adgPath = $this->storeAdgFile($adgFile, $fileId, $timestamp);
        $processedFiles['adg_file_path'] = $adgPath;
        $processedFiles['file_size'] = $adgFile->getSize();

        // 2. Process preview audio
        if ($previewAudio) {
            $audioPath = $this->storePreviewAudio($previewAudio, $fileId, $timestamp);
            $processedFiles['preview_audio_path'] = $audioPath;
        }

        // 3. Process preview image
        if ($previewImage) {
            $imagePath = $this->storePreviewImage($previewImage, $fileId, $timestamp);
            $processedFiles['preview_image_path'] = $imagePath;
        }

        // 4. Queue background processing jobs
        ProcessWorkflowFile::dispatch($adgPath, $fileId);
        
        if ($previewAudio) {
            GenerateAudioPreview::dispatch($processedFiles['preview_audio_path'], $fileId);
        }

        return $processedFiles;
    }

    protected function storeAdgFile(UploadedFile $file, string $fileId, string $timestamp): string
    {
        // Validate ADG file
        $this->validateAdgFile($file);

        // Store with organized path structure
        $path = "workflows/{$timestamp}/{$fileId}/" . $file->getClientOriginalName();
        
        Storage::disk($this->adgDisk)->put($path, $file->getContent(), [
            'ContentType' => 'application/octet-stream',
            'Metadata' => [
                'original_name' => $file->getClientOriginalName(),
                'file_id' => $fileId,
                'uploaded_at' => now()->toISOString(),
            ]
        ]);

        // Create backup copy
        $this->createBackup($path, $file->getContent());

        return $path;
    }

    protected function storePreviewAudio(UploadedFile $file, string $fileId, string $timestamp): string
    {
        $extension = $file->getClientOriginalExtension();
        $path = "previews/audio/{$timestamp}/{$fileId}/preview.{$extension}";
        
        Storage::disk($this->mediaDisk)->put($path, $file->getContent(), [
            'ContentType' => $file->getMimeType(),
            'CacheControl' => 'max-age=31536000', // 1 year
        ]);

        return $path;
    }

    protected function storePreviewImage(UploadedFile $file, string $fileId, string $timestamp): string
    {
        $extension = $file->getClientOriginalExtension();
        $path = "previews/images/{$timestamp}/{$fileId}/preview.{$extension}";
        
        Storage::disk($this->mediaDisk)->put($path, $file->getContent(), [
            'ContentType' => $file->getMimeType(),
            'CacheControl' => 'max-age=31536000', // 1 year
        ]);

        return $path;
    }

    protected function validateAdgFile(UploadedFile $file): void
    {
        // Check file extension
        if (strtolower($file->getClientOriginalExtension()) !== 'adg') {
            throw new \InvalidArgumentException('File must be an .adg file');
        }

        // Check file size (max 10MB)
        if ($file->getSize() > 10 * 1024 * 1024) {
            throw new \InvalidArgumentException('ADG file size cannot exceed 10MB');
        }

        // Basic file structure validation
        $content = $file->getContent();
        
        // ADG files are gzipped XML, check for gzip header
        if (substr($content, 0, 2) !== "\x1f\x8b") {
            throw new \InvalidArgumentException('Invalid ADG file format');
        }

        // Try to decompress and validate XML structure
        try {
            $decompressed = gzdecode($content);
            $xml = simplexml_load_string($decompressed);
            
            if (!$xml || !isset($xml->GroupDeviceChain)) {
                throw new \InvalidArgumentException('Invalid ADG file structure');
            }
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Corrupted ADG file');
        }
    }

    protected function createBackup(string $path, string $content): void
    {
        // Asynchronously backup to secondary storage
        dispatch(function () use ($path, $content) {
            Storage::disk($this->backupDisk)->put($path, $content);
        })->delay(now()->addMinutes(5));
    }

    public function generateSignedDownloadUrl(string $path, int $expiresInMinutes = 60): string
    {
        return Storage::disk($this->adgDisk)->temporaryUrl($path, now()->addMinutes($expiresInMinutes));
    }

    public function deleteWorkflowFiles(string $adgPath, ?string $audioPath = null, ?string $imagePath = null): void
    {
        // Delete from primary storage
        Storage::disk($this->adgDisk)->delete($adgPath);
        
        if ($audioPath) {
            Storage::disk($this->mediaDisk)->delete($audioPath);
        }
        
        if ($imagePath) {
            Storage::disk($this->mediaDisk)->delete($imagePath);
        }

        // Schedule backup deletion
        dispatch(function () use ($adgPath) {
            Storage::disk($this->backupDisk)->delete($adgPath);
        })->delay(now()->addDays(30)); // Keep backups for 30 days
    }
}
```

### Background Processing Jobs

```php
<?php
// app/Jobs/ProcessWorkflowFile.php

use App\Services\AbletonRackAnalyzer;
use App\Models\Workflow;

class ProcessWorkflowFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;

    public function __construct(
        protected string $filePath,
        protected string $fileId
    ) {}

    public function handle(AbletonRackAnalyzer $analyzer): void
    {
        try {
            // Download and analyze the ADG file
            $content = Storage::disk('s3')->get($this->filePath);
            $analysis = $analyzer->analyzeRackContent($content);

            // Update workflow with analysis data
            $workflow = Workflow::where('adg_file_path', $this->filePath)->first();
            
            if ($workflow) {
                $workflow->update([
                    'devices_used' => $analysis['devices'] ?? [],
                    'macro_controls' => $analysis['macro_controls'] ?? [],
                    'ableton_version' => $analysis['ableton_version'] ?? null,
                ]);

                // Dispatch tag extraction job
                ExtractWorkflowTags::dispatch($workflow, $analysis);
            }

        } catch (\Exception $e) {
            \Log::error('Workflow file processing failed', [
                'file_path' => $this->filePath,
                'file_id' => $this->fileId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}

// app/Jobs/GenerateAudioPreview.php
class GenerateAudioPreview implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes
    public $tries = 2;

    public function __construct(
        protected string $audioPath,
        protected string $fileId
    ) {}

    public function handle(): void
    {
        try {
            $content = Storage::disk('s3_public')->get($this->audioPath);
            $tempPath = storage_path('app/temp/' . $this->fileId . '_original');
            
            file_put_contents($tempPath, $content);

            // Generate compressed preview (128kbps MP3, max 30 seconds)
            $previewPath = storage_path('app/temp/' . $this->fileId . '_preview.mp3');
            
            $ffmpeg = \FFMpeg\FFMpeg::create();
            $audio = $ffmpeg->open($tempPath);
            
            // Trim to 30 seconds if longer
            $audio->filters()->clip(\FFMpeg\Coordinate\TimeCode::fromSeconds(0), \FFMpeg\Coordinate\TimeCode::fromSeconds(30));
            
            $format = new \FFMpeg\Format\Audio\Mp3();
            $format->setAudioKiloBitrate(128);
            
            $audio->save($format, $previewPath);

            // Upload optimized preview
            $optimizedPath = str_replace('/preview.', '/preview_optimized.', $this->audioPath);
            Storage::disk('s3_public')->put($optimizedPath, file_get_contents($previewPath), [
                'ContentType' => 'audio/mpeg',
                'CacheControl' => 'max-age=31536000',
            ]);

            // Update workflow with optimized preview path
            $workflow = Workflow::where('preview_audio_path', $this->audioPath)->first();
            if ($workflow) {
                $workflow->update(['preview_audio_path' => $optimizedPath]);
            }

            // Cleanup temp files
            unlink($tempPath);
            unlink($previewPath);

        } catch (\Exception $e) {
            \Log::error('Audio preview generation failed', [
                'audio_path' => $this->audioPath,
                'file_id' => $this->fileId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}
```

### Upload Controller

```php
<?php
// app/Http/Controllers/WorkflowUploadController.php

class WorkflowUploadController extends Controller
{
    public function __construct(
        protected WorkflowFileService $fileService
    ) {}

    public function store(WorkflowUploadRequest $request)
    {
        $this->authorize('create', Workflow::class);

        DB::transaction(function () use ($request) {
            try {
                // Process file uploads
                $fileData = $this->fileService->processWorkflowUpload(
                    $request->file('adg_file'),
                    $request->file('preview_audio'),
                    $request->file('preview_image')
                );

                // Create workflow record
                $workflow = Workflow::create([
                    'user_id' => auth()->id(),
                    'title' => $request->title,
                    'description' => $request->description,
                    'slug' => Str::slug($request->title . '-' . Str::random(8)),
                    'rack_type' => $request->rack_type,
                    'genre' => $request->genre,
                    'bpm' => $request->bpm,
                    'key_signature' => $request->key_signature,
                    'difficulty_level' => $request->difficulty_level,
                    'is_published' => $request->boolean('is_published', false),
                    ...$fileData
                ]);

                // Handle tags
                if ($request->has('tags')) {
                    $workflow->syncTags($request->tags);
                }

                return new WorkflowResource($workflow);

            } catch (\Exception $e) {
                // Cleanup uploaded files on error
                if (isset($fileData)) {
                    $this->fileService->deleteWorkflowFiles(
                        $fileData['adg_file_path'] ?? null,
                        $fileData['preview_audio_path'] ?? null,
                        $fileData['preview_image_path'] ?? null
                    );
                }

                throw $e;
            }
        });
    }

    public function download(Workflow $workflow)
    {
        $this->authorize('download', $workflow);

        // Track download
        WorkflowDownload::create([
            'workflow_id' => $workflow->id,
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Increment download counter
        $workflow->increment('downloads_count');

        // Generate signed URL for secure download
        $downloadUrl = $this->fileService->generateSignedDownloadUrl($workflow->adg_file_path);

        return response()->json([
            'download_url' => $downloadUrl,
            'filename' => basename($workflow->adg_file_path),
            'expires_in_minutes' => 60,
        ]);
    }
}
```

## Media Library Integration

### Custom Path Generator

```php
<?php
// app/MediaLibrary/CustomPathGenerator.php

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class CustomPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        $modelType = class_basename($media->model_type);
        $modelId = $media->model_id;
        $date = $media->created_at->format('Y/m/d');
        
        return "{$modelType}/{$date}/{$modelId}/";
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media) . 'conversions/';
    }
}
```

### User Avatar Management

```php
<?php
// app/Http/Controllers/UserAvatarController.php

class UserAvatarController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120', // 5MB max
        ]);

        $user = auth()->user();

        // Remove existing avatar
        $user->clearMediaCollection('avatar');

        // Add new avatar with automatic conversion
        $media = $user->addMediaFromRequest('avatar')
            ->usingName('avatar')
            ->usingFileName(Str::uuid() . '.' . $request->file('avatar')->getClientOriginalExtension())
            ->toMediaCollection('avatar');

        return response()->json([
            'avatar_url' => $user->fresh()->avatar_url,
            'avatar_thumb_url' => $user->fresh()->avatar_thumb_url,
        ]);
    }

    public function destroy()
    {
        auth()->user()->clearMediaCollection('avatar');
        
        return response()->json(['message' => 'Avatar removed successfully']);
    }
}
```

## Storage Optimization

### CDN Integration

```php
<?php
// config/services.php

'cloudfront' => [
    'distribution_id' => env('CLOUDFRONT_DISTRIBUTION_ID'),
    'key_pair_id' => env('CLOUDFRONT_KEY_PAIR_ID'),
    'private_key_path' => env('CLOUDFRONT_PRIVATE_KEY_PATH'),
    'url' => env('CLOUDFRONT_URL'),
],
```

### Storage Monitoring Service

```php
<?php
// app/Services/StorageMonitoringService.php

class StorageMonitoringService
{
    public function getStorageUsage(): array
    {
        return [
            'total_workflows' => Workflow::count(),
            'total_file_size' => Workflow::sum('file_size'),
            'storage_by_user' => $this->getStorageByUser(),
            'storage_by_type' => $this->getStorageByType(),
            'monthly_uploads' => $this->getMonthlyUploads(),
        ];
    }

    protected function getStorageByUser(): Collection
    {
        return User::withCount('workflows')
            ->with(['workflows' => function ($query) {
                $query->select('user_id', DB::raw('SUM(file_size) as total_size'))
                      ->groupBy('user_id');
            }])
            ->having('workflows_count', '>', 0)
            ->orderByDesc('workflows_count')
            ->limit(100)
            ->get();
    }

    protected function getStorageByType(): array
    {
        return Workflow::selectRaw('rack_type, COUNT(*) as count, SUM(file_size) as total_size')
            ->groupBy('rack_type')
            ->get()
            ->toArray();
    }

    protected function getMonthlyUploads(): Collection
    {
        return Workflow::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as uploads, SUM(file_size) as total_size')
            ->where('created_at', '>=', now()->subYear())
            ->groupBy('month')
            ->orderBy('month')
            ->get();
    }
}
```

## File Security and Access Control

### Signed URL Middleware

```php
<?php
// app/Http/Middleware/ValidateSignedUrl.php

class ValidateSignedUrl
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Invalid or expired download link');
        }

        // Additional validation for workflow downloads
        if ($request->route('workflow')) {
            $workflow = $request->route('workflow');
            
            if (!$workflow->is_published && $workflow->user_id !== auth()->id()) {
                abort(403, 'Unauthorized access to workflow');
            }
        }

        return $next($request);
    }
}
```

This file storage architecture provides:
- Secure, scalable storage using AWS S3
- Organized file structure with proper metadata
- Background processing for file analysis and optimization
- CDN integration for fast global delivery
- Comprehensive error handling and cleanup
- Storage monitoring and analytics
- Security controls with signed URLs and access validation

The system is designed to handle the unique requirements of Ableton workflow files while maintaining performance and security at scale.