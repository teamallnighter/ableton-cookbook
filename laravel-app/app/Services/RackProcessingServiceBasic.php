<?php

namespace App\Services;

use App\Models\Rack;
use App\Models\JobExecution;
use App\Services\AbletonRackAnalyzer\AbletonRackAnalyzer;
use App\Services\AbletonEditionDetector;
use App\Enums\RackProcessingStatus;
use App\Enums\FailureCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Exception;

/**
 * Main orchestration service for rack processing workflow
 * 
 * This is a basic implementation that can work independently of the other services
 * while still providing comprehensive error handling and progress tracking.
 */
class RackProcessingService
{
    /**
     * Process a rack through all analysis stages
     */
    public function processRack(JobExecution $jobExecution): array
    {
        $rack = $jobExecution->model;
        $jobId = $jobExecution->job_id;
        
        try {
            // Start job execution tracking
            $this->startJobExecution($jobExecution);
            
            // Stage 1: File validation
            $this->validateRackFile($rack);
            
            // Stage 2: Analysis preparation
            $filePath = $this->prepareFileForAnalysis($rack);
            
            // Stage 3: Rack structure analysis
            $analysisResults = $this->performRackAnalysis($filePath, $rack);
            
            // Stage 4: Data processing and enrichment
            $processedData = $this->processAnalysisResults($analysisResults, $rack);
            
            // Stage 5: Database updates
            $this->saveRackData($rack, $processedData);
            
            // Stage 6: Finalization
            $this->finalizeProcessing($rack);
            
            // Complete job
            $this->completeJobExecution($jobExecution, $processedData);
            
            Log::info('Rack processing completed successfully', [
                'rack_id' => $rack->id,
                'job_id' => $jobId,
                'processing_time' => $jobExecution->execution_time
            ]);
            
            return [
                'success' => true,
                'job_id' => $jobId,
                'rack_id' => $rack->id,
                'processing_time' => $jobExecution->execution_time,
                'result_data' => $processedData
            ];
            
        } catch (Exception $e) {
            return $this->handleProcessingFailure($jobExecution, $e);
        }
    }
    
    /**
     * Handle processing failure with basic error analysis
     */
    public function handleProcessingFailure(JobExecution $jobExecution, Exception $exception): array
    {
        $rack = $jobExecution->model;
        $jobId = $jobExecution->job_id;
        
        // Basic failure categorization
        $category = $this->categorizeFailure($exception);
        
        // Log the error
        Log::error('Rack processing failed', [
            'job_id' => $jobId,
            'rack_id' => $rack->id,
            'category' => $category->value,
            'error_message' => $exception->getMessage(),
            'error_file' => $exception->getFile(),
            'error_line' => $exception->getLine()
        ]);
        
        // Update job execution with failure details
        $jobExecution->update([
            'status' => 'failed',
            'failed_at' => now(),
            'attempts' => $jobExecution->attempts + 1,
            'failure_category' => $category->value,
            'failure_reason' => $exception->getMessage(),
            'stack_trace' => $exception->getTraceAsString()
        ]);
        
        // Determine if retry is warranted
        $shouldRetry = $this->shouldRetry($category, $jobExecution->attempts);
        
        if ($shouldRetry) {
            return $this->scheduleRetry($jobExecution, $category);
        } else {
            return $this->handlePermanentFailure($jobExecution, $category);
        }
    }
    
    // Helper methods for processing stages
    
    private function validateRackFile(Rack $rack): void
    {
        $fullPath = Storage::disk('private')->path($rack->file_path);
        
        if (!file_exists($fullPath)) {
            throw new Exception("Rack file not found: {$fullPath}");
        }
        
        $fileSizeBytes = filesize($fullPath);
        if ($fileSizeBytes > 100 * 1024 * 1024) { // 100MB limit
            throw new Exception('File too large for processing');
        }
    }
    
    private function prepareFileForAnalysis(Rack $rack): string
    {
        $fullPath = Storage::disk('private')->path($rack->file_path);
        $fileSize = filesize($fullPath);
        
        // Set optimal memory and time limits based on file size
        $memoryLimit = max(512, min(2048, $fileSize / (1024 * 1024) * 4));
        ini_set('memory_limit', $memoryLimit . 'M');
        set_time_limit(600);
        
        return $fullPath;
    }
    
    private function performRackAnalysis(string $filePath, Rack $rack): array
    {
        try {
            $xml = AbletonRackAnalyzer::decompressAndParseAbletonFile($filePath);
            if (!$xml) {
                throw new Exception('Failed to parse ADG file - file may be corrupted');
            }
            
            $typeInfo = AbletonRackAnalyzer::detectRackTypeAndDevice($xml);
            $rackInfo = AbletonRackAnalyzer::parseChainsAndDevices($xml, $rack->original_filename);
            $versionInfo = AbletonRackAnalyzer::extractAbletonVersionInfo($xml);
            
            $editionDetector = new AbletonEditionDetector();
            $abletonEdition = $editionDetector->detectRequiredEdition($rackInfo['chains'] ?? []);
            
            $chainCount = count($rackInfo['chains'] ?? []);
            $deviceCount = $this->countDevices($rackInfo['chains'] ?? []);
            $category = $this->detectCategory($rackInfo['chains'] ?? []);
            
            return [
                'type_info' => $typeInfo,
                'rack_info' => $rackInfo,
                'version_info' => $versionInfo,
                'ableton_edition' => $abletonEdition,
                'chain_count' => $chainCount,
                'device_count' => $deviceCount,
                'category' => $category,
            ];
            
        } catch (Exception $e) {
            throw new Exception("Rack analysis failed: " . $e->getMessage(), 0, $e);
        }
    }
    
    private function processAnalysisResults(array $analysisResults, Rack $rack): array
    {
        return [
            'rack_type' => $analysisResults['type_info'][0] ?? 'AudioEffectGroupDevice',
            'category' => $analysisResults['category'],
            'device_count' => $analysisResults['device_count'],
            'chain_count' => $analysisResults['chain_count'],
            'ableton_version' => $analysisResults['version_info']['ableton_version'] ?? null,
            'ableton_edition' => $analysisResults['ableton_edition'],
            'macro_controls' => $analysisResults['rack_info']['macro_controls'] ?? [],
            'devices' => $this->flattenDevices($analysisResults['rack_info']['chains'] ?? []),
            'chains' => $analysisResults['rack_info']['chains'] ?? [],
            'version_details' => $analysisResults['version_info'],
            'parsing_errors' => $analysisResults['rack_info']['parsing_errors'] ?? [],
            'parsing_warnings' => $analysisResults['rack_info']['parsing_warnings'] ?? [],
            'status' => 'pending',
            'published_at' => now()
        ];
    }
    
    private function saveRackData(Rack $rack, array $processedData): void
    {
        DB::transaction(function () use ($rack, $processedData) {
            $rack->update($processedData);
            $this->clearRelatedCaches($rack);
        });
    }
    
    private function finalizeProcessing(Rack $rack): void
    {
        // Clean up resources
        ini_restore('memory_limit');
    }
    
    private function startJobExecution(JobExecution $jobExecution): void
    {
        $jobExecution->update([
            'status' => 'processing',
            'started_at' => now()
        ]);
    }
    
    private function completeJobExecution(JobExecution $jobExecution, array $resultData): void
    {
        $executionTime = now()->diffInMilliseconds($jobExecution->started_at);
        $memoryPeak = memory_get_peak_usage(true);
        
        $jobExecution->update([
            'status' => 'completed',
            'completed_at' => now(),
            'execution_time' => $executionTime,
            'memory_peak' => $memoryPeak,
            'result_data' => $resultData
        ]);
    }
    
    private function categorizeFailure(Exception $exception): FailureCategory
    {
        $message = strtolower($exception->getMessage());
        
        if (str_contains($message, 'file not found') || str_contains($message, 'no such file')) {
            return FailureCategory::FILE_NOT_FOUND;
        }
        
        if (str_contains($message, 'too large') || str_contains($message, 'file size')) {
            return FailureCategory::FILE_TOO_LARGE;
        }
        
        if (str_contains($message, 'corrupted') || str_contains($message, 'parse') || str_contains($message, 'invalid')) {
            return FailureCategory::FILE_CORRUPTED;
        }
        
        if (str_contains($message, 'memory') || str_contains($message, 'allowed memory size')) {
            return FailureCategory::MEMORY_LIMIT;
        }
        
        if (str_contains($message, 'timeout') || str_contains($message, 'time limit')) {
            return FailureCategory::TIMEOUT;
        }
        
        if (str_contains($message, 'database') || str_contains($message, 'sql')) {
            return FailureCategory::DATABASE_ERROR;
        }
        
        return FailureCategory::UNKNOWN_ERROR;
    }
    
    private function shouldRetry(FailureCategory $category, int $attempts): bool
    {
        $maxRetries = match($category) {
            FailureCategory::NETWORK_ERROR => 5,
            FailureCategory::TIMEOUT => 3,
            FailureCategory::MEMORY_LIMIT => 2,
            FailureCategory::DATABASE_ERROR => 5,
            FailureCategory::UNKNOWN_ERROR => 3,
            default => 0
        };
        
        return $category->isRetryable() && $attempts < $maxRetries;
    }
    
    private function scheduleRetry(JobExecution $jobExecution, FailureCategory $category): array
    {
        $retryDelay = $this->getRetryDelay($category, $jobExecution->attempts);
        $nextRetryAt = now()->addSeconds($retryDelay);
        
        $jobExecution->update([
            'status' => 'retry_scheduled',
            'next_retry_at' => $nextRetryAt,
            'retry_delay' => $retryDelay
        ]);
        
        Log::info('Retry scheduled for rack processing', [
            'rack_id' => $jobExecution->model_id,
            'job_id' => $jobExecution->job_id,
            'attempt' => $jobExecution->attempts,
            'retry_delay' => $retryDelay,
            'next_retry_at' => $nextRetryAt
        ]);
        
        return [
            'success' => false,
            'retry_scheduled' => true,
            'job_id' => $jobExecution->job_id,
            'next_retry_at' => $nextRetryAt,
            'failure_category' => $category->value,
            'user_message' => $category->userMessage()
        ];
    }
    
    private function handlePermanentFailure(JobExecution $jobExecution, FailureCategory $category): array
    {
        $jobExecution->update([
            'status' => 'permanently_failed'
        ]);
        
        Log::error('Rack processing permanently failed', [
            'rack_id' => $jobExecution->model_id,
            'job_id' => $jobExecution->job_id,
            'total_attempts' => $jobExecution->attempts,
            'failure_category' => $category->value
        ]);
        
        return [
            'success' => false,
            'permanently_failed' => true,
            'job_id' => $jobExecution->job_id,
            'failure_category' => $category->value,
            'user_message' => $category->userMessage(),
            'suggested_actions' => $category->suggestedActions()
        ];
    }
    
    private function getRetryDelay(FailureCategory $category, int $attempt): int
    {
        $baseDelay = match($category) {
            FailureCategory::NETWORK_ERROR => 30,
            FailureCategory::TIMEOUT => 60,
            FailureCategory::MEMORY_LIMIT => 300,
            FailureCategory::DATABASE_ERROR => 30,
            default => 60
        };
        
        // Exponential backoff
        return $baseDelay * pow(2, $attempt - 1);
    }
    
    private function countDevices(array $chains): int
    {
        $count = 0;
        foreach ($chains as $chain) {
            $count += count($chain['devices'] ?? []);
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
    
    private function flattenDevices(array $chains): array
    {
        $devices = [];
        foreach ($chains as $chain) {
            if (isset($chain['devices'])) {
                $devices = array_merge($devices, $chain['devices']);
            }
        }
        return $devices;
    }
    
    private function detectCategory(array $chains): ?string
    {
        $deviceTypes = [];
        foreach ($chains as $chain) {
            foreach ($chain['devices'] ?? [] as $device) {
                $deviceTypes[] = strtolower($device['type'] ?? '');
            }
        }
        
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
    
    private function clearRelatedCaches(Rack $rack): void
    {
        Cache::forget("rack_structure_{$rack->id}");
        Cache::forget('rack_categories');
        Cache::forget('featured_racks_10');
        Cache::forget('popular_racks_10');
    }
}