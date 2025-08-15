<?php

namespace App\Services;

class AbletonEditionDetector
{
    private static $introDevices = [
        // Instruments
        'Drift', 'Drum Rack', 'Instrument Rack', 'Simpler',
        
        // Audio Effects
        'Audio Effect Rack', 'Auto Filter', 'Autopan', 'Auto Pitch', 'Beat Repeat',
        'Channel EQ', 'Ensemble', 'Compressor', 'Delay', 'EQ Three', 'Erosion',
        'Gate', 'Grain Delay', 'Limiter', 'Looper', 'Phaser-Flanger', 'Redux',
        'Reverb', 'Saturator', 'Tuner', 'Utility',
        
        // MIDI Effects & Tools
        'Arpeggiator', 'Arpeggiate', 'CC Control', 'Chord', 'Connect',
        'MIDI Effect Rack', 'MIDI Monitor', 'MPE Control', 'Note Length',
        'Ornament', 'Pitch', 'Quantize', 'Random', 'Recombine',
        'Rhythm', 'Scale', 'Seed', 'Shape', 'Stacks', 'Strum',
        'Time Span', 'Time Warp', 'Velocity'
    ];

    private static $standardDevices = [
        // Instruments (includes all Intro + these)
        'Analog', 'Bass', 'Collision', 'Drum Synths', 'Electric', 
        'External Instrument', 'Operator', 'Poli', 'Tension',
        
        // Audio Effects (includes all Intro + these)
        'Corpus', 'CV Clock In', 'CV Clock Out', 'CV Envelope Follower',
        'CV Instrument', 'CV LFO', 'CV Shaper', 'CV Utility', 'Drum Buss',
        'Dynamic Tube', 'EQ Eight', 'External Audio Effect', 'Filter Delay',
        'Glue Compressor', 'Multiband Dynamics', 'Overdrive', 'Resonators',
        'Shifter', 'Spectrum', 'Vinyl Distortion', 'Vocoder'
    ];

    private static $suiteOnlyDevices = [
        // Instruments (Suite only)
        'DS Analog', 'DS Drum', 'DS Penta', 'Granulator III', 'Meld', 
        'Sampler', 'Wavetable',
        
        // Audio Effects (Suite only)
        'Amp', 'Cabinet', 'Color Limiter', 'Convolution Reverb', 'Echo',
        'Gated Delay', 'Hybrid Reverb', 'Pedal', 'Pitch Hack', 'PitchLoop89',
        'Re-Enveloper', 'Roar', 'Spectral Blur', 'Spectral Resonator',
        'Spectral Time', 'Surround Panner',
        
        // MIDI Effects (Suite only)
        'Expression Control', 'Melodic Steps', 'MIDI Euclidean Generator',
        'MIDI Velocity Shaper', 'Note Echo', 'Pattern'
    ];

    /**
     * Determine the minimum Ableton Live edition required based on devices used
     */
    public static function detectRequiredEdition(array $devices): string
    {
        $deviceNames = self::extractDeviceNames($devices);
        
        // Check for Suite-only devices first
        foreach ($deviceNames as $deviceName) {
            if (self::isSuiteOnlyDevice($deviceName)) {
                return 'suite';
            }
        }
        
        // Check for Standard devices (not in Intro)
        foreach ($deviceNames as $deviceName) {
            if (self::isStandardDevice($deviceName) && !self::isIntroDevice($deviceName)) {
                return 'standard';
            }
        }
        
        // Default to intro if only intro devices are used
        return 'intro';
    }

    /**
     * Extract device names from device data array
     */
    private static function extractDeviceNames(array $devices): array
    {
        $names = [];
        
        foreach ($devices as $device) {
            if (is_array($device)) {
                // Handle nested device structures
                $name = $device['name'] ?? $device['type'] ?? null;
                if ($name) {
                    $names[] = self::normalizeDeviceName($name);
                }
                
                // Check for nested devices in chains
                if (isset($device['chains']) && is_array($device['chains'])) {
                    foreach ($device['chains'] as $chain) {
                        if (isset($chain['devices']) && is_array($chain['devices'])) {
                            $names = array_merge($names, self::extractDeviceNames($chain['devices']));
                        }
                    }
                }
            } elseif (is_string($device)) {
                $names[] = self::normalizeDeviceName($device);
            }
        }
        
        return array_unique($names);
    }

    /**
     * Normalize device names for comparison
     */
    private static function normalizeDeviceName(string $name): string
    {
        // Remove common prefixes/suffixes and normalize
        $name = preg_replace('/^(Ableton|Live|Device)[\s\-_]*/i', '', $name);
        $name = preg_replace('/[\s\-_]*(Device|Effect|Instrument)$/i', '', $name);
        
        return trim($name);
    }

    /**
     * Check if device is available in Intro
     */
    private static function isIntroDevice(string $deviceName): bool
    {
        return in_array($deviceName, self::$introDevices, true);
    }

    /**
     * Check if device is available in Standard
     */
    private static function isStandardDevice(string $deviceName): bool
    {
        return in_array($deviceName, array_merge(self::$introDevices, self::$standardDevices), true);
    }

    /**
     * Check if device is Suite-only
     */
    private static function isSuiteOnlyDevice(string $deviceName): bool
    {
        return in_array($deviceName, self::$suiteOnlyDevices, true);
    }

    /**
     * Get all devices for a specific edition
     */
    public static function getDevicesForEdition(string $edition): array
    {
        switch ($edition) {
            case 'intro':
                return self::$introDevices;
            case 'standard':
                return array_merge(self::$introDevices, self::$standardDevices);
            case 'suite':
                return array_merge(self::$introDevices, self::$standardDevices, self::$suiteOnlyDevices);
            default:
                return [];
        }
    }
}