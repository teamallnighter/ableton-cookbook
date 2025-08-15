<?php

namespace App\Http\Controllers;

use App\Models\Rack;
use App\Models\Tag;
use App\Services\AbletonRackAnalyzer\AbletonRackAnalyzer;
use App\Services\AbletonEditionDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RackUploadController extends Controller
{
    // Middleware is now applied in routes/web.php

    public function create()
    {
        return view('racks.upload');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'rack_file' => 'required|file|adg_file|max:10240', // 10MB max
            'tags' => 'nullable|string|max:500'
        ]);

        try {
            // Handle file upload
            $file = $request->file('rack_file');
            $originalFilename = $file->getClientOriginalName();
            
            // Generate unique filename
            $hash = hash_file('sha256', $file->getPathname());
            $filename = Str::uuid() . '.adg';
            
            // Check for duplicate files
            $existingRack = Rack::where('file_hash', $hash)->first();
            if ($existingRack) {
                throw ValidationException::withMessages([
                    'rack_file' => 'This rack file has already been uploaded.'
                ]);
            }
            
            // Store the file
            $filePath = $file->storeAs('racks', $filename, 'private');
            $fullPath = storage_path('app/private/' . $filePath);
            
            // Analyze the rack file
            $xml = AbletonRackAnalyzer::decompressAndParseAbletonFile($fullPath);
            if (!$xml) {
                throw ValidationException::withMessages([
                    'rack_file' => 'Invalid or corrupted Ableton rack file.'
                ]);
            }
            
            // Get rack analysis
            $typeInfo = AbletonRackAnalyzer::detectRackTypeAndDevice($xml);
            $rackInfo = AbletonRackAnalyzer::parseChainsAndDevices($xml, $originalFilename);
            $versionInfo = AbletonRackAnalyzer::extractAbletonVersionInfo($xml);
            
            // Calculate counts
            $chainCount = count($rackInfo['chains'] ?? []);
            $deviceCount = $this->countDevices($rackInfo['chains'] ?? []);
            
            // Detect category from devices
            $category = $this->detectCategory($rackInfo['chains'] ?? []);
            
            // Detect Ableton edition requirement
            $editionDetector = new AbletonEditionDetector();
            $abletonEdition = $editionDetector->detectRequiredEdition($rackInfo['chains'] ?? []);
            
            // Create the rack
            $rack = Rack::create([
                'uuid' => Str::uuid(),
                'user_id' => auth()->id(),
                'title' => $request->title,
                'description' => $request->description,
                'slug' => Str::slug($request->title),
                'file_path' => $filePath,
                'file_hash' => $hash,
                'file_size' => $file->getSize(),
                'original_filename' => $originalFilename,
                'rack_type' => $typeInfo['rack_type'] ?? 'AudioEffectGroupDevice',
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
                'status' => 'pending', // Require approval
                'published_at' => null,
                'is_public' => true
            ]);
            
            // Handle tags
            if ($request->tags) {
                $this->attachTags($rack, $request->tags);
            }
            
            return redirect()->route('racks.show', $rack)
                ->with('success', 'Rack uploaded successfully! It will be reviewed before being published.');
                
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['rack_file' => 'Failed to process rack file: ' . $e->getMessage()]);
        }
    }
    
    private function countDevices($chains)
    {
        $count = 0;
        foreach ($chains as $chain) {
            $count += count($chain['devices'] ?? []);
            // Count nested devices
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
    
    private function flattenDevices($chains)
    {
        $devices = [];
        foreach ($chains as $chain) {
            if (isset($chain['devices'])) {
                $devices = array_merge($devices, $chain['devices']);
            }
        }
        return $devices;
    }
    
    private function detectCategory($chains)
    {
        // Simple category detection based on common device patterns
        $deviceTypes = [];
        foreach ($chains as $chain) {
            foreach ($chain['devices'] ?? [] as $device) {
                $deviceTypes[] = strtolower($device['type'] ?? '');
            }
        }
        
        // Category mapping logic
        if (in_array('operator', $deviceTypes) || in_array('collision', $deviceTypes)) {
            return 'Instruments';
        } elseif (in_array('overdrive', $deviceTypes) || in_array('saturator', $deviceTypes)) {
            return 'Distortion';
        } elseif (in_array('chorus', $deviceTypes) || in_array('phaser', $deviceTypes)) {
            return 'Modulation';
        } elseif (in_array('delay', $deviceTypes) || in_array('reverb', $deviceTypes)) {
            return 'Time';
        } elseif (in_array('eq3', $deviceTypes) || in_array('compressor', $deviceTypes)) {
            return 'Mixing';
        } else {
            return null; // Let user categorize manually later
        }
    }
    
    private function attachTags($rack, $tagString)
    {
        $tagNames = array_filter(array_map('trim', explode(',', $tagString)));
        
        foreach ($tagNames as $tagName) {
            if (strlen($tagName) > 2) { // Minimum tag length
                $tag = Tag::firstOrCreate([
                    'name' => $tagName,
                    'slug' => Str::slug($tagName)
                ]);
                
                $rack->tags()->attach($tag->id);
            }
        }
    }
}