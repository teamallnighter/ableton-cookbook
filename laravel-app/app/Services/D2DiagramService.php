<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class D2DiagramService 
{
    public function generateRackDiagram(array $rackData, array $options = []): string
    {
        $rackName = $rackData['title'] ?? 'Unknown Rack';
        
        // Get actual chain and device data from rack file
        $chainData = $this->getChainData($rackData);
        
        $d2 = "direction: right\n";
        $d2 .= "# {$rackName} Rack\n\n";
        
        if (empty($chainData)) {
            // Fallback for racks without chain data
            $d2 .= "rack: {$rackName} {\n";
            $d2 .= "  style.fill: '#ff6b6b'\n";
            $d2 .= "}\n";
        } else {
            // Create diagram with parallel chains
            $d2 .= "rack: {$rackName} {\n";
            
            // Add each chain
            foreach ($chainData as $i => $chain) {
                $chainName = $this->sanitizeName($chain['name'] ?? "Chain_" . ($i + 1));
                $devices = $chain['devices'] ?? [];
                
                $d2 .= "  {$chainName}: " . ($chain['name'] ?? "Chain " . ($i + 1)) . " {\n";
                
                if (empty($devices)) {
                    $d2 .= "    empty: Empty\n";
                } else {
                    // Track device name counts to make them unique
                    $deviceCounts = [];
                    $uniqueDeviceNames = [];

                    // First pass: create unique identifiers for each device
                    foreach ($devices as $index => $device) {
                        $baseName = $this->sanitizeName($device['name'] ?? 'Unknown');

                        // Count occurrences of this device name
                        if (!isset($deviceCounts[$baseName])) {
                            $deviceCounts[$baseName] = 0;
                        }
                        $deviceCounts[$baseName]++;

                        // Create unique identifier by appending index if there are duplicates
                        if ($deviceCounts[$baseName] > 1) {
                            $uniqueName = $baseName . '_' . $deviceCounts[$baseName];
                        } else {
                            // Check if we'll have duplicates later
                            $duplicateCount = 0;
                            foreach ($devices as $checkDevice) {
                                if (($checkDevice['name'] ?? 'Unknown') === ($device['name'] ?? 'Unknown')) {
                                    $duplicateCount++;
                                }
                            }
                            $uniqueName = $duplicateCount > 1 ? $baseName . '_1' : $baseName;
                        }

                        $uniqueDeviceNames[$index] = $uniqueName;
                    }

                    // Second pass: create the diagram with unique names
                    $prevDevice = null;
                    foreach ($devices as $index => $device) {
                        $deviceName = $uniqueDeviceNames[$index];
                        $displayName = $device['name'] ?? 'Unknown';
                        $d2 .= "    {$deviceName}: \"{$displayName}\"\n";

                        if ($prevDevice) {
                            $d2 .= "    {$prevDevice} -> {$deviceName}\n";
                        }
                        $prevDevice = $deviceName;
                    }
                }
                $d2 .= "  }\n";
            }
            
            $d2 .= "}\n\n";
            
            // Add input/output outside the rack (since they're not in JSON)
            $d2 .= "# Signal Flow\n";
            $d2 .= "input: Rack Input {\n";
            $d2 .= "  style.fill: '#2ecc71'\n";
            $d2 .= "}\n\n";
            $d2 .= "output: Rack Output {\n";
            $d2 .= "  style.fill: '#e74c3c'\n";
            $d2 .= "}\n\n";
            
            foreach ($chainData as $i => $chain) {
                $chainName = $this->sanitizeName($chain['name'] ?? "Chain_" . ($i + 1));
                $d2 .= "input -> rack.{$chainName}\n";
                $d2 .= "rack.{$chainName} -> output\n";
            }
        }
        
        return $d2;
    }
    
    private function getChainData(array $rackData): array
    {
        // Try to get from already parsed data
        if (!empty($rackData['chains'])) {
            return $rackData['chains'];
        }
        
        // If no chains in rack data, try to analyze the file
        if (!empty($rackData['uuid'])) {
            try {
                $rack = \App\Models\Rack::where('uuid', $rackData['uuid'])->first();
                if ($rack && $rack->file_path) {
                    $filePath = storage_path('app/private/' . $rack->file_path);
                    if (file_exists($filePath)) {
                        // First decompress and get XML
                        $xml = \App\Services\AbletonRackAnalyzer\AbletonRackAnalyzer::decompressAndParseAbletonFile($filePath);
                        if ($xml) {
                            // Then parse the chains and devices
                            $result = \App\Services\AbletonRackAnalyzer\AbletonRackAnalyzer::parseChainsAndDevices($xml, $filePath);
                            return $result['chains'] ?? [];
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::error("Failed to analyze rack file: " . $e->getMessage());
            }
        }
        
        return [];
    }
    
    private function getDeviceData(array $rackData): array
    {
        // Try to get from already parsed data
        if (!empty($rackData['devices'])) {
            return $rackData['devices'];
        }
        
        // If no devices in rack data, try to analyze the file
        if (!empty($rackData['uuid'])) {
            try {
                $rack = \App\Models\Rack::where('uuid', $rackData['uuid'])->first();
                if ($rack && $rack->file_path) {
                    $filePath = storage_path('app/private/' . $rack->file_path);
                    if (file_exists($filePath)) {
                        // First decompress and get XML
                        $xml = \App\Services\AbletonRackAnalyzer\AbletonRackAnalyzer::decompressAndParseAbletonFile($filePath);
                        if ($xml) {
                            // Then parse the chains and devices
                            $result = \App\Services\AbletonRackAnalyzer\AbletonRackAnalyzer::parseChainsAndDevices($xml, $filePath);
                            return $result['chains'][0]['devices'] ?? [];
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::error("Failed to analyze rack file: " . $e->getMessage());
            }
        }
        
        return [];
    }
    
    private function sanitizeName(string $name): string
    {
        // Convert device name to valid D2 identifier
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
    }
    
    private function getDeviceColor(string $deviceName): string
    {
        $name = strtolower($deviceName);
        
        if (strpos($name, 'eq') !== false) return '#96CEB4';
        if (strpos($name, 'compressor') !== false) return '#45B7D1';
        if (strpos($name, 'reverb') !== false) return '#48CAE4';
        if (strpos($name, 'delay') !== false) return '#FECA57';
        if (strpos($name, 'distortion') !== false || strpos($name, 'overdrive') !== false) return '#FF9F43';
        if (strpos($name, 'filter') !== false) return '#A55EEA';
        if (strpos($name, 'bass') !== false || strpos($name, 'operator') !== false) return '#FF6B6B';
        
        return '#4ECDC4'; // Default device color
    }

    public function generateDrumRackDiagram(array $rackData, array $options = []): string
    {
        $rackName = $rackData['title'] ?? 'Unknown Drum Rack';
        
        $d2 = "# {$rackName} Drum Rack\n\n";
        $d2 .= "drumrack: {$rackName} {\n";
        $d2 .= "  style.fill: '#4ecdc4'\n";
        $d2 .= "}\n";
        
        return $d2;
    }

    public function generateAsciiDiagram(array $rackData): string
    {
        $rackName = $rackData['title'] ?? 'Unknown Rack';
        $chainData = $this->getChainData($rackData);

        if (empty($chainData)) {
            return "┌─────────────────────────────────────┐\n│  {$rackName}\n│  (No devices found)\n└─────────────────────────────────────┘";
        }

        $ascii = '';
        $ascii .= "┌─ {$rackName} " . str_repeat('─', max(0, 40 - strlen($rackName))) . "┐\n";

        foreach ($chainData as $chainIndex => $chain) {
            $chainName = $chain['name'] ?? "Chain " . ($chainIndex + 1);
            $devices = $chain['devices'] ?? [];

            $ascii .= "│\n";
            $ascii .= "├─ {$chainName}:\n";

            if (empty($devices)) {
                $ascii .= "│   (Empty)\n";
            } else {
                foreach ($devices as $deviceIndex => $device) {
                    $deviceName = $device['name'] ?? 'Unknown Device';
                    $isLast = ($deviceIndex === count($devices) - 1);
                    $connector = $isLast ? '└──' : '├──';
                    $arrow = $isLast ? '' : ' ↓';

                    $ascii .= "│   {$connector} {$deviceName}{$arrow}\n";
                }
            }
        }

        $ascii .= "└" . str_repeat('─', 44) . "┘";

        return $ascii;
    }

    public function renderDiagram(string $d2Code, string $format = 'svg'): ?string
    {
        try {
            $tempFile = tempnam(sys_get_temp_dir(), 'd2_');
            file_put_contents($tempFile . '.d2', $d2Code);

            // Handle ASCII format specially - use stdout
            if ($format === 'ascii') {
                $command = "d2 --layout=elk --stdout-format ascii {$tempFile}.d2 - 2>&1";

                exec($command, $output, $returnCode);
                unlink($tempFile . '.d2');

                if ($returnCode === 0) {
                    // Remove the "success:" line that D2 adds at the end
                    $result = implode("\n", $output);
                    $result = preg_replace('/\nsuccess:.*$/m', '', $result);
                    return trim($result);
                }
            } else {
                // Handle other formats with file output
                $outputFile = $tempFile . '.' . $format;
                $command = "d2 --layout=elk {$tempFile}.d2 {$outputFile} 2>&1";

                exec($command, $output, $returnCode);

                if ($returnCode === 0 && file_exists($outputFile)) {
                    $result = file_get_contents($outputFile);
                    unlink($tempFile . '.d2');
                    unlink($outputFile);
                    return $result;
                }

                unlink($tempFile . '.d2');
            }

            Log::error('D2 rendering failed', [
                'return_code' => $returnCode,
                'output' => $output,
                'd2_code' => $d2Code,
                'format' => $format
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('D2 rendering failed: ' . $e->getMessage());
            return null;
        }
    }
}