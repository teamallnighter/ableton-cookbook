<?php

namespace App\Services;

use App\Models\Rack;
use App\Models\User;
use App\Services\AbletonRackAnalyzer\AbletonRackAnalyzer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class RackProcessingService
{
    protected AbletonRackAnalyzer $analyzer;

    public function __construct()
    {
        $this->analyzer = new AbletonRackAnalyzer();
    }

    /**
     * Process an uploaded rack file
     */
    public function processRack(UploadedFile $file, User $user, array $metadata = []): Rack
    {
        return DB::transaction(function () use ($file, $user, $metadata) {
            // 1. Store file securely
            $fileInfo = $this->storeRackFile($file);
            
            // 2. Create database record with pending status
            $rack = Rack::create([
                'uuid' => Str::uuid(),
                'user_id' => $user->id,
                'title' => $metadata['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'description' => $metadata['description'] ?? null,
                'slug' => $this->generateUniqueSlug($metadata['title'] ?? $file->getClientOriginalName()),
                'file_path' => $fileInfo['path'],
                'file_hash' => $fileInfo['hash'],
                'file_size' => $fileInfo['size'],
                'original_filename' => $file->getClientOriginalName(),
                'status' => 'processing',
                'is_public' => $metadata['is_public'] ?? true,
            ]);
            
            // 3. Process the rack file with analyzer
            try {
                $analysisResult = $this->analyzeRackFile($fileInfo['full_path']);
                
                if ($analysisResult) {
                    // Update rack with analysis results
                    $rack->update([
                        'rack_type' => $analysisResult['rack_type'] ?? null,
                        'device_count' => count($analysisResult['chains'][0]['devices'] ?? []),
                        'chain_count' => count($analysisResult['chains'] ?? []),
                        'ableton_version' => $analysisResult['ableton_version'] ?? null,
                        'macro_controls' => $analysisResult['macro_controls'] ?? [],
                        'devices' => $analysisResult['chains'] ?? [],
                        'chains' => $analysisResult['chains'] ?? [],
                        'version_details' => $analysisResult['version_details'] ?? [],
                        'parsing_errors' => $analysisResult['parsing_errors'] ?? [],
                        'parsing_warnings' => $analysisResult['parsing_warnings'] ?? [],
                        'status' => empty($analysisResult['parsing_errors']) ? 'approved' : 'pending',
                        'published_at' => empty($analysisResult['parsing_errors']) ? now() : null,
                    ]);
                    
                    // Process tags if provided
                    if (!empty($metadata['tags'])) {
                        $this->attachTags($rack, $metadata['tags']);
                    }
                }
            } catch (Exception $e) {
                $rack->update([
                    'status' => 'failed',
                    'processing_error' => $e->getMessage(),
                ]);
                
                throw $e;
            }
            
            return $rack->fresh();
        });
    }

    /**
     * Analyze a rack file using AbletonRackAnalyzer
     */
    protected function analyzeRackFile(string $filePath): ?array
    {
        $xml = AbletonRackAnalyzer::decompressAndParseAbletonFile($filePath);
        
        if (!$xml) {
            throw new Exception('Failed to decompress or parse the .adg file');
        }
        
        return AbletonRackAnalyzer::parseChainsAndDevices($xml, $filePath);
    }

    /**
     * Store rack file securely
     */
    protected function storeRackFile(UploadedFile $file): array
    {
        $uuid = Str::uuid();
        $extension = $file->getClientOriginalExtension();
        $path = "racks/{$uuid}.{$extension}";
        
        // Store in private storage
        $storedPath = Storage::disk('private')->putFileAs('racks', $file, "{$uuid}.{$extension}");
        
        // Get full path for analysis
        $fullPath = Storage::disk('private')->path($storedPath);
        
        return [
            'path' => $storedPath,
            'full_path' => $fullPath,
            'hash' => hash_file('sha256', $fullPath),
            'size' => $file->getSize(),
        ];
    }

    /**
     * Generate unique slug for rack
     */
    protected function generateUniqueSlug(string $title): string
    {
        $slug = Str::slug($title);
        $count = 1;
        
        while (Rack::where('slug', $slug)->exists()) {
            $slug = Str::slug($title) . '-' . $count;
            $count++;
        }
        
        return $slug;
    }

    /**
     * Attach tags to rack
     */
    protected function attachTags(Rack $rack, array $tags): void
    {
        $tagIds = [];
        
        foreach ($tags as $tagName) {
            $tag = \App\Models\Tag::firstOrCreate(
                ['slug' => Str::slug($tagName)],
                ['name' => $tagName]
            );
            
            $tagIds[] = $tag->id;
            $tag->increment('usage_count');
        }
        
        $rack->tags()->sync($tagIds);
    }

    /**
     * Check if rack file is duplicate
     */
    public function isDuplicate(string $fileHash): ?Rack
    {
        return Rack::where('file_hash', $fileHash)
            ->where('status', '!=', 'failed')
            ->first();
    }

    /**
     * Generate preview for rack (placeholder for future implementation)
     */
    public function generatePreview(Rack $rack): void
    {
        // Future: Generate audio preview
        // This would require integration with audio processing tools
        // or user-uploaded preview files
    }
}