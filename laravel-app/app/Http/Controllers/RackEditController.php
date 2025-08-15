<?php

namespace App\Http\Controllers;

use App\Models\Rack;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RackEditController extends Controller
{
    public function edit(Rack $rack)
    {
        // Check if user owns this rack
        if (auth()->id() !== $rack->user_id) {
            abort(403, 'Unauthorized');
        }

        $categories = [
            'Distortion',
            'Modulation', 
            'Time',
            'Mixing',
            'Instruments',
            'Drums',
            'Vocal',
            'Guitar',
            'Bass',
            'Creative',
            'Mastering'
        ];

        return view('racks.edit', compact('rack', 'categories'));
    }

    public function update(Request $request, Rack $rack)
    {
        // Check if user owns this rack
        if (auth()->id() !== $rack->user_id) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'category' => 'nullable|string|max:50',
            'tags' => 'nullable|string|max:500',
            'chain_annotations' => 'nullable|array',
            'chain_annotations.*.custom_name' => 'nullable|string|max:100',
            'chain_annotations.*.note' => 'nullable|string|max:500'
        ]);

        // Update rack
        $rack->update([
            'title' => $request->title,
            'slug' => Str::slug($request->title),
            'description' => $request->description,
            'category' => $request->category,
            'chain_annotations' => $request->chain_annotations
        ]);

        // Update tags
        if ($request->tags) {
            // Detach old tags
            $rack->tags()->detach();
            
            // Attach new tags
            $tagNames = array_filter(array_map('trim', explode(',', $request->tags)));
            foreach ($tagNames as $tagName) {
                if (strlen($tagName) > 2) {
                    $tag = Tag::firstOrCreate([
                        'name' => $tagName,
                        'slug' => Str::slug($tagName)
                    ]);
                    $rack->tags()->attach($tag->id);
                }
            }
        } else {
            // Remove all tags if none provided
            $rack->tags()->detach();
        }

        return redirect()->route('racks.show', $rack)
            ->with('success', 'Rack updated successfully!');
    }
}