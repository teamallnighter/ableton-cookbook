<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * D2 Diagram Service for Ableton Cookbook
 * 
 * Transforms Ableton rack analysis data into beautiful D2 diagrams
 * Supporting drum racks, device chains, parallel processing, and advanced visualizations
 */
class D2DiagramService 
{
    private const MIDI_NOTE_MAP = [
        36 => 'C1 (Kick)', 37 => 'C#1', 38 => 'D1 (Snare)', 39 => 'D#1',
        40 => 'E1 (Rim)', 41 => 'F1', 42 => 'F#1 (HH Closed)', 43 => 'G1',
        44 => 'G#1 (HH Foot)', 45 => 'A1 (Tom Low)', 46 => 'A#1 (HH Open)', 47 => 'B1 (Tom Mid)',
        48 => 'C2 (Tom High)', 49 => 'C#2 (Crash)', 50 => 'D2 (Tom High)', 51 => 'D#2 (Ride)'
    ];

    private const DEVICE_CATEGORIES = [
        'synthesizer' => ['color' => '#ff6b6b', 'icon' => '♪'},
        'sampler' => ['color' => '#4ecdc4', 'icon' => '⚡'],
        'audio_effect' => ['color' => '#45b7d1', 'icon' => '~'],
        'midi_effect' => ['color' => '#96ceb4', 'icon' => '⌘'],
        'drum' => ['color' => '#feca57', 'icon' => '🥁'],
        'utility' => ['color' => '#a55eea', 'icon' => '⚙']
    ];

    /**
     * Generate a complete D2 diagram from rack analysis data
     */
    public function generateRackDiagram(array $rackData, string $style = 'sketch'): string
    {
        $rackType = $rackData['rack_type'] ?? 'unknown';
        $rackName = $rackData['rack_name'] ?? 'Unknown Rack';
        
        // Start with theme and title
        $d2 = $this->generateHeader($rackName, $rackType, $style);
        
        // Add rack-specific visualization
        switch ($rackType) {
            case 'DrumRack':
                $d2 .= $this->generateDrumRackVisualization($rackData);
                break;
            case 'InstrumentGroupDevice':
                $d2 .= $this->generateInstrumentRackVisualization($rackData);
                break;
            case 'AudioEffectGroupDevice':
                $d2 .= $this->generateAudioEffectRackVisualization($rackData);
                break;
            case 'MidiEffectGroupDevice':
                $d2 .= $this->generateMidiEffectRackVisualization($rackData);
                break;
            default:
                $d2 .= $this->generateGenericRackVisualization($rackData);
        }
        
        // Add macro controls if present
        if (!empty($rackData['macro_controls'])) {
            $d2 .= $this->generateMacroControlsVisualization($rackData['macro_controls']);
        }
        
        // Add performance metrics
        if (isset($rackData['performance_metrics'])) {
            $d2 .= $this->generatePerformanceMetrics($rackData['performance_metrics']);
        }
        
        return $d2;
    }

    /**
     * Generate D2 header with title and theme
     */
    private function generateHeader(string $rackName, string $rackType, string $style): string
    {
        $themes = [
            'sketch' => 'theme: 101',
            'svg' => 'theme: 1',
            'ascii' => 'theme: 200'
        ];

        $theme = $themes[$style] ?? $themes['sketch'];
        
        return "# {$rackName} - {$rackType}\n" .
               "direction: right\n" .
               "{$theme}\n\n" .
               "title: |\n" .
               "  # {$rackName}\n" .
               "  {$rackType} Analysis\n" .
               "| { near: top-center }\n\n";
    }

    /**
     * Generate drum rack 4x4 grid visualization with MIDI mappings
     */
    private function generateDrumRackVisualization(array $rackData): string
    {
        $d2 = "# Drum Rack Layout\n";
        $d2 .= "drum_rack: Drum Rack {\n";
        $d2 .= "  style.fill: '#2a2a2a'\n";
        $d2 .= "  style.stroke: '#feca57'\n";
        $d2 .= "  style.stroke-width: 3\n\n";

        // Create 4x4 grid for drum pads
        $padLayout = $this->generateDrumPadGrid($rackData);
        $d2 .= $padLayout;

        $d2 .= "}\n\n";

        // Add connections showing signal flow
        if (!empty($rackData['chains'])) {
            $d2 .= $this->generateDrumChainConnections($rackData['chains']);
        }

        return $d2;
    }

    /**
     * Generate drum pad grid with MIDI note mappings and devices
     */
    private function generateDrumPadGrid(array $rackData): string
    {
        $d2 = "";
        $chains = $rackData['chains'] ?? [];
        $padMapping = $rackData['drum_statistics']['pad_mapping'] ?? [];

        // Generate 4x4 grid (C1-D#2, MIDI notes 36-51)
        $rows = ['C', 'C#', 'D', 'D#'];
        $octaves = ['1', '1', '1', '1', '2', '2', '2', '2'];
        
        for ($row = 0; $row < 4; $row++) {
            $d2 .= "  row_{$row}: {\n";
            $d2 .= "    style.fill: 'transparent'\n";
            
            for ($col = 0; $col < 4; $col++) {
                $midiNote = 36 + ($row * 4) + $col;
                $noteName = self::MIDI_NOTE_MAP[$midiNote] ?? "Note {$midiNote}";
                
                // Find corresponding chain/device
                $device = $this->findDeviceForMidiNote($chains, $midiNote);
                $deviceName = $device ? $device['name'] : 'Empty';
                
                $padId = "pad_{$row}_{$col}";
                $d2 .= "    {$padId}: |\n";
                $d2 .= "      {$noteName}\n";
                $d2 .= "      {$deviceName}\n";
                $d2 .= "    | {\n";
                
                if ($device) {
                    $category = $this->categorizeDevice($device);
                    $color = self::DEVICE_CATEGORIES[$category]['color'] ?? '#666';
                    $d2 .= "      style.fill: '{$color}'\n";
                    $d2 .= "      style.stroke: '#fff'\n";
                    $d2 .= "      tooltip: |\n";
                    $d2 .= "        MIDI Note: {$midiNote}\n";
                    $d2 .= "        Device: {$deviceName}\n";
                    $d2 .= "        Type: {$category}\n";
                    if (!empty($device['devices'])) {
                        $d2 .= "        Chain Length: " . count($device['devices']) . "\n";
                    }
                    $d2 .= "      |\n";
                } else {
                    $d2 .= "      style.fill: '#333'\n";
                    $d2 .= "      style.opacity: 0.3\n";
                }
                
                $d2 .= "    }\n";
            }
            $d2 .= "  }\n";
        }

        return $d2;
    }

    /**
     * Generate instrument rack visualization with key/velocity splits
     */
    private function generateInstrumentRackVisualization(array $rackData): string
    {
        $d2 = "# Instrument Rack\n";
        $d2 .= "instrument_rack: Instrument Rack {\n";
        $d2 .= "  style.fill: '#1a1a2e'\n";
        $d2 .= "  style.stroke: '#ff6b6b'\n";
        $d2 .= "  style.stroke-width: 3\n\n";

        $chains = $rackData['chains'] ?? [];
        
        foreach ($chains as $index => $chain) {
            $chainId = "chain_" . ($index + 1);
            $chainName = $chain['name'] ?? "Chain " . ($index + 1);
            
            $d2 .= "  {$chainId}: |\n";
            $d2 .= "    {$chainName}\n";
            
            // Add key range info if available
            if (!empty($chain['annotations']['key_range'])) {
                $keyRange = $chain['annotations']['key_range'];
                $d2 .= "    Keys: {$keyRange['low_key']}-{$keyRange['high_key']}\n";
            }
            
            // Add velocity range info if available
            if (!empty($chain['annotations']['velocity_range'])) {
                $velRange = $chain['annotations']['velocity_range'];
                $d2 .= "    Vel: {$velRange['low_vel']}-{$velRange['high_vel']}\n";
            }
            
            $d2 .= "  | {\n";
            $d2 .= "    style.fill: '#16213e'\n";
            
            if ($chain['is_soloed'] ?? false) {
                $d2 .= "    style.stroke: '#ffd700'\n";
                $d2 .= "    style.stroke-width: 2\n";
            }
            
            $d2 .= "  }\n\n";
            
            // Add devices in chain
            if (!empty($chain['devices'])) {
                $d2 .= $this->generateDeviceChain($chain['devices'], $chainId);
            }
        }

        $d2 .= "}\n\n";

        // Add signal flow connections
        $d2 .= $this->generateInstrumentSignalFlow($chains);

        return $d2;
    }

    /**
     * Generate audio effect rack with parallel/serial processing visualization
     */
    private function generateAudioEffectRackVisualization(array $rackData): string
    {
        $d2 = "# Audio Effect Rack\n";
        $d2 .= "audio_input: Input {\n";
        $d2 .= "  style.fill: '#4ecdc4'\n";
        $d2 .= "  icon: https://icons.terrastruct.com/essentials%2F114-audio.svg\n";
        $d2 .= "}\n\n";

        $d2 .= "effect_rack: Audio Effects {\n";
        $d2 .= "  style.fill: '#1a1a2e'\n";
        $d2 .= "  style.stroke: '#45b7d1'\n";
        $d2 .= "  style.stroke-width: 3\n\n";

        $chains = $rackData['chains'] ?? [];
        
        // Determine if processing is parallel or serial
        $isParallel = count($chains) > 1;
        
        if ($isParallel) {
            $d2 .= "  # Parallel Processing\n";
            foreach ($chains as $index => $chain) {
                $chainId = "fx_chain_" . ($index + 1);
                $d2 .= $this->generateEffectChain($chain, $chainId, $index);
            }
        } else {
            $d2 .= "  # Serial Processing\n";
            if (!empty($chains[0]['devices'])) {
                $d2 .= $this->generateDeviceChain($chains[0]['devices'], "serial_chain");
            }
        }

        $d2 .= "}\n\n";

        $d2 .= "audio_output: Output {\n";
        $d2 .= "  style.fill: '#4ecdc4'\n";
        $d2 .= "  icon: https://icons.terrastruct.com/essentials%2F114-audio.svg\n";
        $d2 .= "}\n\n";

        // Add signal flow
        $d2 .= "audio_input -> effect_rack -> audio_output\n\n";

        return $d2;
    }

    /**
     * Generate device chain visualization
     */
    private function generateDeviceChain(array $devices, string $parentId): string
    {
        $d2 = "";
        
        foreach ($devices as $index => $device) {
            $deviceId = "{$parentId}_device_" . ($index + 1);
            $deviceName = $device['name'] ?? 'Unknown Device';
            $deviceType = $device['type'] ?? 'unknown';
            
            $category = $this->categorizeDevice($device);
            $categoryInfo = self::DEVICE_CATEGORIES[$category] ?? self::DEVICE_CATEGORIES['utility'];
            
            $d2 .= "  {$deviceId}: |\n";
            $d2 .= "    {$categoryInfo['icon']} {$deviceName}\n";
            if (isset($device['preset_name'])) {
                $d2 .= "    ({$device['preset_name']})\n";
            }
            $d2 .= "  | {\n";
            $d2 .= "    style.fill: '{$categoryInfo['color']}'\n";
            $d2 .= "    style.stroke: '#fff'\n";
            
            if (!($device['is_on'] ?? true)) {
                $d2 .= "    style.opacity: 0.4\n";
            }
            
            $d2 .= "    tooltip: |\n";
            $d2 .= "      Device: {$deviceName}\n";
            $d2 .= "      Type: {$deviceType}\n";
            $d2 .= "      Category: {$category}\n";
            $d2 .= "      Status: " . (($device['is_on'] ?? true) ? 'On' : 'Off') . "\n";
            
            // Add nested chains for group devices
            if (!empty($device['chains'])) {
                $d2 .= "      Nested Chains: " . count($device['chains']) . "\n";
            }
            
            $d2 .= "    |\n";
            $d2 .= "  }\n\n";
            
            // Create signal flow connections
            if ($index > 0) {
                $prevDeviceId = "{$parentId}_device_{$index}";
                $d2 .= "  {$prevDeviceId} -> {$deviceId}\n";
            }
        }
        
        return $d2;
    }

    /**
     * Generate macro controls visualization
     */
    private function generateMacroControlsVisualization(array $macroControls): string
    {
        $d2 = "# Macro Controls\n";
        $d2 .= "macros: Macro Controls {\n";
        $d2 .= "  style.fill: '#2d1b69'\n";
        $d2 .= "  style.stroke: '#a55eea'\n";
        $d2 .= "  style.stroke-width: 2\n\n";

        foreach ($macroControls as $macro) {
            $macroId = "macro_" . ($macro['index'] + 1);
            $macroName = $macro['name'] ?? "Macro " . ($macro['index'] + 1);
            $macroValue = round($macro['value'] ?? 0, 2);
            
            $d2 .= "  {$macroId}: |\n";
            $d2 .= "    {$macroName}\n";
            $d2 .= "    Value: {$macroValue}\n";
            $d2 .= "  | {\n";
            $d2 .= "    style.fill: '#7b68ee'\n";
            
            // Visual indicator based on value (0-1 range)
            $intensity = $macroValue;
            if ($intensity > 0.7) {
                $d2 .= "    style.stroke: '#ff6b6b'\n";
            } elseif ($intensity > 0.3) {
                $d2 .= "    style.stroke: '#feca57'\n";
            } else {
                $d2 .= "    style.stroke: '#48cae4'\n";
            }
            
            $d2 .= "    tooltip: |\n";
            $d2 .= "      Macro: {$macroName}\n";
            $d2 .= "      Current Value: {$macroValue}\n";
            $d2 .= "      Range: 0.0 - 1.0\n";
            $d2 .= "    |\n";
            $d2 .= "  }\n\n";
        }

        $d2 .= "}\n\n";
        return $d2;
    }

    /**
     * Generate performance metrics visualization
     */
    private function generatePerformanceMetrics(array $metrics): string
    {
        $d2 = "# Performance Analysis\n";
        $d2 .= "performance: Performance Metrics {\n";
        $d2 .= "  style.fill: '#1a1a2e'\n";
        $d2 .= "  style.stroke: '#96ceb4'\n";
        $d2 .= "  style.stroke-width: 2\n\n";

        $complexity = $metrics['complexity_score'] ?? 50;
        $cpuUsage = $metrics['cpu_usage'] ?? 'medium';
        
        $d2 .= "  complexity: |\n";
        $d2 .= "    🎯 Complexity\n";
        $d2 .= "    Score: {$complexity}/100\n";
        $d2 .= "  | {\n";
        
        if ($complexity > 75) {
            $d2 .= "    style.fill: '#ff6b6b'\n"; // High complexity - red
        } elseif ($complexity > 50) {
            $d2 .= "    style.fill: '#feca57'\n"; // Medium complexity - yellow
        } else {
            $d2 .= "    style.fill: '#48cae4'\n"; // Low complexity - blue
        }
        
        $d2 .= "  }\n\n";

        $d2 .= "  cpu: |\n";
        $d2 .= "    💻 CPU Usage\n";
        $d2 .= "    Level: {$cpuUsage}\n";
        $d2 .= "  | {\n";
        
        switch ($cpuUsage) {
            case 'high':
                $d2 .= "    style.fill: '#ff6b6b'\n";
                break;
            case 'medium':
                $d2 .= "    style.fill: '#feca57'\n";
                break;
            default:
                $d2 .= "    style.fill: '#48cae4'\n";
        }
        
        $d2 .= "  }\n\n";

        if (isset($metrics['device_count'])) {
            $d2 .= "  devices: |\n";
            $d2 .= "    🔧 Device Count\n";
            $d2 .= "    Total: {$metrics['device_count']}\n";
            $d2 .= "  | {\n";
            $d2 .= "    style.fill: '#a55eea'\n";
            $d2 .= "  }\n\n";
        }

        $d2 .= "}\n\n";
        return $d2;
    }

    /**
     * Generate comparison diagram between two racks
     */
    public function generateComparisonDiagram(array $rackA, array $rackB): string
    {
        $d2 = "# Rack Comparison\n";
        $d2 .= "direction: right\n";
        $d2 .= "theme: 101\n\n";

        $d2 .= "comparison: Rack Comparison {\n";
        $d2 .= "  style.fill: '#1a1a2e'\n\n";

        // Rack A
        $nameA = $rackA['rack_name'] ?? 'Rack A';
        $d2 .= "  rack_a: |\n";
        $d2 .= "    📁 {$nameA}\n";
        $d2 .= "    Devices: " . count($rackA['chains'][0]['devices'] ?? []) . "\n";
        $d2 .= "    Chains: " . count($rackA['chains'] ?? []) . "\n";
        $d2 .= "  | {\n";
        $d2 .= "    style.fill: '#ff6b6b'\n";
        $d2 .= "  }\n\n";

        // Rack B
        $nameB = $rackB['rack_name'] ?? 'Rack B';
        $d2 .= "  rack_b: |\n";
        $d2 .= "    📁 {$nameB}\n";
        $d2 .= "    Devices: " . count($rackB['chains'][0]['devices'] ?? []) . "\n";
        $d2 .= "    Chains: " . count($rackB['chains'] ?? []) . "\n";
        $d2 .= "  | {\n";
        $d2 .= "    style.fill: '#4ecdc4'\n";
        $d2 .= "  }\n\n";

        // Comparison metrics
        $d2 .= "  metrics: Comparison {\n";
        $d2 .= "    complexity_diff: |\n";
        $d2 .= "      Complexity Difference\n";
        $complexityA = $rackA['performance_metrics']['complexity_score'] ?? 50;
        $complexityB = $rackB['performance_metrics']['complexity_score'] ?? 50;
        $diff = $complexityA - $complexityB;
        $d2 .= "      {$diff} points\n";
        $d2 .= "    |\n";
        $d2 .= "  }\n\n";

        $d2 .= "  rack_a -> metrics\n";
        $d2 .= "  rack_b -> metrics\n";
        $d2 .= "}\n";

        return $d2;
    }

    /**
     * Generate Laravel model relationships diagram
     */
    public function generateModelRelationshipDiagram(): string
    {
        $d2 = "# Ableton Cookbook - Database Schema\n";
        $d2 .= "direction: right\n";
        $d2 .= "theme: 1\n\n";

        $d2 .= "database: Ableton Cookbook Database {\n";
        $d2 .= "  style.fill: '#1a1a2e'\n\n";

        // Core models
        $d2 .= "  users: Users {\n";
        $d2 .= "    id: int\n";
        $d2 .= "    name: string\n";
        $d2 .= "    email: string\n";
        $d2 .= "    style.fill: '#ff6b6b'\n";
        $d2 .= "  }\n\n";

        $d2 .= "  racks: Racks {\n";
        $d2 .= "    id: int\n";
        $d2 .= "    uuid: string\n";
        $d2 .= "    title: string\n";
        $d2 .= "    rack_type: string\n";
        $d2 .= "    devices: json\n";
        $d2 .= "    chains: json\n";
        $d2 .= "    style.fill: '#4ecdc4'\n";
        $d2 .= "  }\n\n";

        $d2 .= "  collections: Enhanced Collections {\n";
        $d2 .= "    id: int\n";
        $d2 .= "    title: string\n";
        $d2 .= "    type: enum\n";
        $d2 .= "    is_published: boolean\n";
        $d2 .= "    style.fill: '#45b7d1'\n";
        $d2 .= "  }\n\n";

        $d2 .= "  learning_paths: Learning Paths {\n";
        $d2 .= "    id: int\n";
        $d2 .= "    title: string\n";
        $d2 .= "    difficulty_level: enum\n";
        $d2 .= "    estimated_duration: int\n";
        $d2 .= "    style.fill: '#96ceb4'\n";
        $d2 .= "  }\n\n";

        // Relationships
        $d2 .= "  users -> racks: creates\n";
        $d2 .= "  users -> collections: creates\n";
        $d2 .= "  users -> learning_paths: enrolls\n";
        $d2 .= "  collections -> racks: contains\n";
        $d2 .= "  learning_paths -> collections: includes\n";

        $d2 .= "}\n";

        return $d2;
    }

    /**
     * Categorize device based on type and name patterns
     */
    private function categorizeDevice(array $device): string
    {
        $deviceType = strtolower($device['type'] ?? '');
        $deviceName = strtolower($device['name'] ?? '');

        // Drum devices
        if (str_contains($deviceName, 'kick') || str_contains($deviceName, 'snare') || 
            str_contains($deviceName, 'hat') || str_contains($deviceName, 'cymbal')) {
            return 'drum';
        }

        // Synthesizers
        if (str_contains($deviceType, 'analog') || str_contains($deviceType, 'operator') || 
            str_contains($deviceType, 'wavetable') || str_contains($deviceName, 'synth')) {
            return 'synthesizer';
        }

        // Samplers
        if (str_contains($deviceType, 'simpler') || str_contains($deviceType, 'sampler') || 
            str_contains($deviceType, 'impulse')) {
            return 'sampler';
        }

        // MIDI Effects
        if (str_contains($deviceType, 'midi') || str_contains($deviceType, 'arpeggiator') || 
            str_contains($deviceType, 'chord')) {
            return 'midi_effect';
        }

        // Audio Effects
        if (str_contains($deviceType, 'reverb') || str_contains($deviceType, 'delay') || 
            str_contains($deviceType, 'compressor') || str_contains($deviceType, 'eq')) {
            return 'audio_effect';
        }

        return 'utility';
    }

    /**
     * Find device associated with specific MIDI note in drum rack
     */
    private function findDeviceForMidiNote(array $chains, int $midiNote): ?array
    {
        foreach ($chains as $chain) {
            // Check if this chain has MIDI note mapping
            if (isset($chain['midi_note']) && $chain['midi_note'] == $midiNote) {
                return $chain;
            }
            
            // Check chain name for note patterns
            $chainName = strtolower($chain['name'] ?? '');
            $noteName = strtolower(self::MIDI_NOTE_MAP[$midiNote] ?? '');
            
            if (str_contains($chainName, str_before($noteName, ' (')) || 
                str_contains($chainName, str_after($noteName, ' ('))) {
                return $chain;
            }
        }

        return null;
    }

    /**
     * Generate effect chain with parallel processing indicators
     */
    private function generateEffectChain(array $chain, string $chainId, int $index): string
    {
        $d2 = "  {$chainId}: |\n";
        $d2 .= "    Chain " . ($index + 1) . "\n";
        $d2 .= "    {$chain['name']}\n";
        $d2 .= "  | {\n";
        $d2 .= "    style.fill: '#16213e'\n";
        
        if ($chain['is_soloed'] ?? false) {
            $d2 .= "    style.stroke: '#ffd700'\n";
        }
        
        $d2 .= "  }\n\n";

        // Add devices in this effect chain
        if (!empty($chain['devices'])) {
            $d2 .= $this->generateDeviceChain($chain['devices'], $chainId);
        }

        return $d2;
    }

    /**
     * Generate signal flow for instrument rack
     */
    private function generateInstrumentSignalFlow(array $chains): string
    {
        $d2 = "# Signal Flow\n";
        
        if (count($chains) > 1) {
            // Parallel instrument processing
            $d2 .= "midi_in: MIDI Input {\n";
            $d2 .= "  style.fill: '#96ceb4'\n";
            $d2 .= "}\n\n";
            
            $d2 .= "audio_out: Audio Output {\n";
            $d2 .= "  style.fill: '#4ecdc4'\n";
            $d2 .= "}\n\n";
            
            foreach ($chains as $index => $chain) {
                $chainId = "chain_" . ($index + 1);
                $d2 .= "midi_in -> instrument_rack.{$chainId}\n";
                $d2 .= "instrument_rack.{$chainId} -> audio_out\n";
            }
        }

        return $d2;
    }

    /**
     * Generate drum chain connections
     */
    private function generateDrumChainConnections(array $chains): string
    {
        $d2 = "# Drum Signal Flow\n";
        $d2 .= "midi_input: MIDI Input {\n";
        $d2 .= "  style.fill: '#96ceb4'\n";
        $d2 .= "}\n\n";
        
        $d2 .= "audio_mix: Audio Mix {\n";
        $d2 .= "  style.fill: '#4ecdc4'\n";
        $d2 .= "}\n\n";
        
        $d2 .= "midi_input -> drum_rack\n";
        $d2 .= "drum_rack -> audio_mix\n";
        
        return $d2;
    }

    /**
     * Generate generic rack visualization for unknown types
     */
    private function generateGenericRackVisualization(array $rackData): string
    {
        $d2 = "# Generic Rack\n";
        $d2 .= "rack: " . ($rackData['rack_name'] ?? 'Unknown Rack') . " {\n";
        $d2 .= "  style.fill: '#1a1a2e'\n";
        $d2 .= "  style.stroke: '#a55eea'\n\n";

        $chains = $rackData['chains'] ?? [];
        
        foreach ($chains as $index => $chain) {
            $chainId = "chain_" . ($index + 1);
            $d2 .= $this->generateDeviceChain($chain['devices'] ?? [], $chainId);
        }

        $d2 .= "}\n\n";
        
        return $d2;
    }

    /**
     * Generate MIDI effect rack visualization
     */
    private function generateMidiEffectRackVisualization(array $rackData): string
    {
        $d2 = "# MIDI Effect Rack\n";
        $d2 .= "midi_input: MIDI Input {\n";
        $d2 .= "  style.fill: '#96ceb4'\n";
        $d2 .= "}\n\n";

        $d2 .= "midi_rack: MIDI Effects {\n";
        $d2 .= "  style.fill: '#1a1a2e'\n";
        $d2 .= "  style.stroke: '#96ceb4'\n";
        $d2 .= "  style.stroke-width: 3\n\n";

        $chains = $rackData['chains'] ?? [];
        
        foreach ($chains as $index => $chain) {
            $chainId = "midi_chain_" . ($index + 1);
            $d2 .= $this->generateDeviceChain($chain['devices'] ?? [], $chainId);
        }

        $d2 .= "}\n\n";

        $d2 .= "midi_output: MIDI Output {\n";
        $d2 .= "  style.fill: '#96ceb4'\n";
        $d2 .= "}\n\n";

        $d2 .= "midi_input -> midi_rack -> midi_output\n";

        return $d2;
    }
}