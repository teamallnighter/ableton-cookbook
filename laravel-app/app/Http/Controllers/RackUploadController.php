<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessRackFileJob;
use App\Models\Rack;
use App\Models\Tag;
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
            
            // Create minimal rack record with pending status
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
                'status' => 'pending', // Will be updated by job
                'is_public' => true
            ]);
            
            // Handle tags
            if ($request->tags) {
                $this->attachTags($rack, $request->tags);
            }
            
            // Dispatch background processing
            ProcessRackFileJob::dispatch($rack);
            
            return redirect()->route('racks.show', $rack)
                ->with('success', 'Rack uploaded successfully! Processing in background...');
                
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['rack_file' => 'Failed to process rack file: ' . $e->getMessage()]);
        }
    }
    
    // Methods moved to ProcessRackFileJob:
    // - countDevices()
    // - flattenDevices() 
    // - detectCategory()
    
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