<?php

namespace App\Livewire;

use App\Models\Rack;
use App\Models\RackRating;
use App\Models\RackFavorite;
use Livewire\Component;
use Illuminate\Support\Facades\Storage;

class RackShow extends Component
{
    public Rack $rack;
    public $rackData;
    public $userRating = 0;
    public $hoveredStar = 0;
    public $isFavorited = false;

    public function mount(Rack $rack)
    {
        $this->rack = $rack;
        
        // Increment view count
        $this->rack->increment('views_count');
        
        // Parse rack structure for tree view
        $this->parseRackStructure();
        
        // Load user's existing rating if authenticated
        $this->loadUserRating();
        
        // Load user's favorite status if authenticated
        $this->loadFavoriteStatus();
    }

    public function downloadRack()
    {
        // Increment download count
        $this->rack->increment('downloads_count');
        
        // Return download response
        if ($this->rack->file_path && Storage::disk('private')->exists($this->rack->file_path)) {
            return Storage::disk('private')->download(
                $this->rack->file_path,
                $this->rack->original_filename ?: $this->rack->slug . '.adg'
            );
        }
        
        session()->flash('error', 'Rack file not found.');
    }

    public function rateRack($rating)
    {
        if (!auth()->check()) {
            session()->flash('error', 'You must be logged in to rate racks.');
            return;
        }

        if ($rating < 1 || $rating > 5) {
            session()->flash('error', 'Invalid rating. Please select 1-5 stars.');
            return;
        }

        // Create or update the user's rating
        RackRating::updateOrCreate(
            [
                'rack_id' => $this->rack->id,
                'user_id' => auth()->id()
            ],
            [
                'rating' => $rating
            ]
        );

        // Update the rack's cached rating statistics
        $this->updateRackRatingStats();
        
        // Update local state
        $this->userRating = $rating;
        $this->rack->refresh();
        
        session()->flash('success', 'Thank you for rating this rack!');
    }

    public function toggleFavorite()
    {
        if (!auth()->check()) {
            session()->flash('error', 'You must be logged in to favorite racks.');
            return;
        }

        $favorite = RackFavorite::where('rack_id', $this->rack->id)
            ->where('user_id', auth()->id())
            ->first();

        if ($favorite) {
            // Remove favorite
            $favorite->delete();
            $this->isFavorited = false;
            session()->flash('success', 'Removed from favorites!');
        } else {
            // Add favorite
            RackFavorite::create([
                'rack_id' => $this->rack->id,
                'user_id' => auth()->id()
            ]);
            $this->isFavorited = true;
            session()->flash('success', 'Added to favorites!');
        }
    }

    public function setHoveredStar($star)
    {
        $this->hoveredStar = $star;
    }

    public function clearHoveredStar()
    {
        $this->hoveredStar = 0;
    }

    public function deleteRack()
    {
        if (!auth()->check() || auth()->id() !== $this->rack->user_id) {
            session()->flash('error', 'You are not authorized to delete this rack.');
            return;
        }

        // Delete the file from storage
        if ($this->rack->file_path && Storage::disk('private')->exists($this->rack->file_path)) {
            Storage::disk('private')->delete($this->rack->file_path);
        }

        // Delete related records (ratings, favorites, etc.)
        $this->rack->ratings()->delete();
        $this->rack->favorites()->delete();
        $this->rack->tags()->detach();

        // Delete the rack record
        $this->rack->delete();

        session()->flash('success', 'Rack deleted successfully.');
        
        // Redirect to user's profile or home
        return redirect()->route('profile');
    }

    private function loadUserRating()
    {
        if (auth()->check()) {
            $rating = RackRating::where('rack_id', $this->rack->id)
                ->where('user_id', auth()->id())
                ->first();
            
            $this->userRating = $rating ? $rating->rating : 0;
        }
    }

    private function updateRackRatingStats()
    {
        $ratings = RackRating::where('rack_id', $this->rack->id)->get();
        
        $this->rack->update([
            'average_rating' => $ratings->avg('rating') ?: 0,
            'ratings_count' => $ratings->count()
        ]);
    }

    private function loadFavoriteStatus()
    {
        if (auth()->check()) {
            $this->isFavorited = RackFavorite::where('rack_id', $this->rack->id)
                ->where('user_id', auth()->id())
                ->exists();
        }
    }

    private function parseRackStructure()
    {
        if ($this->rack->chains) {
            // Use stored chain data if available
            // Check if it's already an array (Laravel auto-decoded) or needs decoding
            $chains = is_array($this->rack->chains) 
                ? $this->rack->chains 
                : json_decode($this->rack->chains, true);
            
            $this->rackData = [
                'chains' => $chains,
                'type' => $this->rack->rack_type
            ];
        } elseif ($this->rack->devices) {
            // Fall back to device data grouped into a single chain
            // Check if it's already an array (Laravel auto-decoded) or needs decoding
            $devices = is_array($this->rack->devices)
                ? $this->rack->devices
                : json_decode($this->rack->devices, true);
                
            $this->rackData = [
                'chains' => $this->groupDevicesIntoChains($devices),
                'type' => $this->rack->rack_type
            ];
        } else {
            // Try to re-analyze from file
            $this->reAnalyzeRackStructure();
        }
    }

    private function groupDevicesIntoChains($devices)
    {
        // Group devices into a single chain for display
        return [
            [
                'name' => 'Chain 1',
                'devices' => $devices
            ]
        ];
    }

    private function reAnalyzeRackStructure()
    {
        if ($this->rack->file_path && Storage::disk('private')->exists($this->rack->file_path)) {
            try {
                $filePath = Storage::disk('private')->path($this->rack->file_path);
                $xml = \App\Services\AbletonRackAnalyzer\AbletonRackAnalyzer::decompressAndParseAbletonFile($filePath);
                $analysis = \App\Services\AbletonRackAnalyzer\AbletonRackAnalyzer::parseChainsAndDevices($xml, $this->rack->title, false);
                
                if ($analysis && isset($analysis['chains'])) {
                    $this->rackData = [
                        'chains' => $analysis['chains'],
                        'type' => $this->rack->rack_type
                    ];
                    
                    // Update the rack with analyzed data for future use
                    $this->rack->update([
                        'chains' => json_encode($analysis['chains']),
                        'devices' => json_encode($this->flattenDevices($analysis['chains']))
                    ]);
                } else {
                    $this->rackData = [
                        'chains' => [],
                        'type' => $this->rack->rack_type
                    ];
                }
            } catch (\Exception $e) {
                $this->rackData = [
                    'chains' => [],
                    'type' => $this->rack->rack_type
                ];
            }
        } else {
            $this->rackData = [
                'chains' => [],
                'type' => $this->rack->rack_type
            ];
        }
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

    public function render()
    {
        return view('livewire.rack-show');
    }
}
