<?php

namespace App\Jobs;

use App\Models\Rack;
use App\Services\AbletonRackAnalyzer\AbletonRackAnalyzer;
use App\Services\AbletonEditionDetector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessRackFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes max
    public $tries = 3;
    public $maxExceptions = 1;

    public function __construct(public Rack $rack)
    {
    }

    public function handle(): void
    {
        try {
            Log::info("Starting processing for rack: {$this->rack->title} (ID: {$this->rack->id})");
            
            $this->rack->update(['status' => 'processing']);
            
            $fullPath = Storage::disk('private')->path($this->rack->file_path);
            
            if (!file_exists($fullPath)) {
                throw new \Exception("Rack file not found: {$fullPath}");
            }
            
            // Perform analysis
            $xml = AbletonRackAnalyzer::decompressAndParseAbletonFile($fullPath);
            if (!$xml) {
                throw new \Exception('Failed to parse ADG file');
            }
            
            $typeInfo = AbletonRackAnalyzer::detectRackTypeAndDevice($xml);
            $rackInfo = AbletonRackAnalyzer::parseChainsAndDevices($xml, $this->rack->original_filename);
            $versionInfo = AbletonRackAnalyzer::extractAbletonVersionInfo($xml);
            
            // Calculate counts
            $chainCount = count($rackInfo['chains'] ?? []);
            $deviceCount = $this->countDevices($rackInfo['chains'] ?? []);
            $category = $this->detectCategory($rackInfo['chains'] ?? []);
            
            // Detect edition requirement
            $editionDetector = new AbletonEditionDetector();
            $abletonEdition = $editionDetector->detectRequiredEdition($rackInfo['chains'] ?? []);
            
            // Update rack with analysis results
            $this->rack->update([
                'rack_type' => $typeInfo[0] ?? 'AudioEffectGroupDevice',
                'category' => $category,
                'device_count' => $deviceCount,
                'chain_count' => $chainCount,
                'ableton_version' => $versionInfo['ableton_version'] ?? null,
                'ableton_edition' => $abletonEdition,
                'macro_controls' => $rackInfo['macro_controls'] ?? [],
                'devices' => $this->flattenDevices($rackInfo['chains'] ?? []),
                'chains' => $rackInfo['chains'] ?? [],
                'version_details' => $versionInfo,
                'parsing_errors' => $rackInfo['parsing_errors'] ?? [],
                'parsing_warnings' => $rackInfo['parsing_warnings'] ?? [],
                'status' => 'approved', // Auto-approve for now
                'published_at' => now()
            ]);
            
            // Clear related caches
            Cache::forget("rack_structure_{$this->rack->id}");
            Cache::forget('rack_categories');
            Cache::forget('featured_racks_10');
            Cache::forget('popular_racks_10');
            
            Log::info("Successfully processed rack: {$this->rack->title} (ID: {$this->rack->id})");
            
        } catch (\Exception $e) {
            Log::error("Failed to process rack {$this->rack->id}: " . $e->getMessage());
            
            $this->rack->update([
                'status' => 'failed',
                'processing_error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    private function countDevices($chains): int
    {
        $count = 0;
        foreach ($chains as $chain) {
            $count += count($chain['devices'] ?? []);
            // Count nested devices in racks
            foreach ($chain['devices'] ?? [] as $device) {
                if (isset($device['chains']) && is_array($device['chains'])) {
                    foreach ($device['chains'] as $nestedChain) {
                        $count += count($nestedChain['devices'] ?? []);
                    }
                }
            }
        }
        return $count;
    }
    
    private function flattenDevices($chains): array
    {
        $devices = [];
        foreach ($chains as $chain) {
            if (isset($chain['devices'])) {
                $devices = array_merge($devices, $chain['devices']);
            }
        }
        return $devices;
    }
    
    private function detectCategory($chains): ?string
    {
        $deviceTypes = [];
        foreach ($chains as $chain) {
            foreach ($chain['devices'] ?? [] as $device) {
                $deviceTypes[] = strtolower($device['type'] ?? '');
            }
        }
        
        // Category detection based on device types
        if (in_array('operator', $deviceTypes) || in_array('collision', $deviceTypes) || in_array('sampler', $deviceTypes)) {
            return 'Instruments';
        } elseif (in_array('overdrive', $deviceTypes) || in_array('saturator', $deviceTypes) || in_array('amp', $deviceTypes)) {
            return 'Distortion';
        } elseif (in_array('chorus', $deviceTypes) || in_array('phaser', $deviceTypes) || in_array('autopan', $deviceTypes)) {
            return 'Modulation';
        } elseif (in_array('delay', $deviceTypes) || in_array('reverb', $deviceTypes) || in_array('echo', $deviceTypes)) {
            return 'Time';
        } elseif (in_array('eq3', $deviceTypes) || in_array('eq8', $deviceTypes) || in_array('compressor', $deviceTypes)) {
            return 'Mixing';
        } elseif (in_array('autofilter', $deviceTypes) || in_array('filterdelay', $deviceTypes)) {
            return 'Filter';
        }
        
        return null;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessRackFileJob failed for rack {$this->rack->id}: " . $exception->getMessage());
        
        $this->rack->update([
            'status' => 'failed',
            'processing_error' => $exception->getMessage()
        ]);
    }
}