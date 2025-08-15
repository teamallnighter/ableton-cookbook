<?php

namespace App\Livewire;

use App\Models\Rack;
use App\Models\RackRating;
use App\Models\RackFavorite;
use App\Models\RackReport;
use App\Notifications\RackRatedNotification;
use App\Jobs\IncrementRackViewsJob;
use Livewire\Component;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class RackShow extends Component
{
    public Rack $rack;
    public $rackData;
    public $userRating = 0;
    public $hoveredStar = 0;
    public $isFavorited = false;
    
    // Report modal state
    public $showReportModal = false;
    public $reportIssueType = '';
    public $reportDescription = '';

    public function mount(Rack $rack)
    {
        $this->rack = $rack->load('user');
        
        // Increment view count asynchronously to avoid blocking response
        IncrementRackViewsJob::dispatch($this->rack->id);
        
        // Parse rack structure for tree view with caching
        $this->parseRackStructure();
        
        // Batch load user interactions to avoid N+1 queries
        $this->loadUserInteractions();
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
        
        // Send notification to rack owner (if not rating their own rack)
        if (auth()->id() !== $this->rack->user_id) {
            $this->rack->user->notify(new RackRatedNotification($this->rack, auth()->user(), $rating));
        }
        
        // Clear cache and update local state
        $this->clearUserInteractionsCache();
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
        
        // Clear user interactions cache after favorite change
        $this->clearUserInteractionsCache();
    }

    public function setHoveredStar($star)
    {
        $this->hoveredStar = $star;
    }

    public function clearHoveredStar()
    {
        $this->hoveredStar = 0;
    }

    public function openReportModal()
    {
        if (!auth()->check()) {
            session()->flash('error', 'You must be logged in to report issues.');
            return;
        }
        
        $this->showReportModal = true;
        $this->reportIssueType = '';
        $this->reportDescription = '';
    }

    public function closeReportModal()
    {
        $this->showReportModal = false;
        $this->reportIssueType = '';
        $this->reportDescription = '';
    }

    public function submitReport()
    {
        if (!auth()->check()) {
            session()->flash('error', 'You must be logged in to report issues.');
            return;
        }

        $this->validate([
            'reportIssueType' => 'required|string',
            'reportDescription' => 'required|string|min:10|max:1000',
        ], [
            'reportIssueType.required' => 'Please select an issue type.',
            'reportDescription.required' => 'Please describe the issue.',
            'reportDescription.min' => 'Description must be at least 10 characters.',
            'reportDescription.max' => 'Description cannot exceed 1000 characters.',
        ]);

        // Check if user already reported this rack recently
        $existingReport = RackReport::where('rack_id', $this->rack->id)
            ->where('user_id', auth()->id())
            ->where('created_at', '>=', now()->subHours(24))
            ->first();

        if ($existingReport) {
            session()->flash('error', 'You already reported this rack within the last 24 hours.');
            return;
        }

        RackReport::create([
            'rack_id' => $this->rack->id,
            'user_id' => auth()->id(),
            'issue_type' => $this->reportIssueType,
            'description' => $this->reportDescription,
            'status' => 'pending'
        ]);

        $this->closeReportModal();
        session()->flash('success', 'Thank you for your report. We\'ll review it and take appropriate action.');
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

    private function loadUserInteractions()
    {
        if (auth()->check()) {
            // Cache user interactions for 5 minutes to avoid repeated queries
            $interactions = Cache::remember(
                "user_interactions_{$this->rack->id}_" . auth()->id(), 
                300, 
                function() {
                    $rating = RackRating::where('rack_id', $this->rack->id)
                        ->where('user_id', auth()->id())
                        ->value('rating');
                    
                    $favorited = RackFavorite::where('rack_id', $this->rack->id)
                        ->where('user_id', auth()->id())
                        ->exists();
                    
                    return [
                        'rating' => $rating ?: 0,
                        'favorited' => $favorited
                    ];
                }
            );
            
            $this->userRating = $interactions['rating'];
            $this->isFavorited = $interactions['favorited'];
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

    private function clearUserInteractionsCache()
    {
        if (auth()->check()) {
            Cache::forget("user_interactions_{$this->rack->id}_" . auth()->id());
        }
    }

    private function parseRackStructure()
    {
        // Cache rack structure data to avoid repeated heavy operations
        $this->rackData = Cache::remember(
            "rack_structure_{$this->rack->id}", 
            3600, // Cache for 1 hour
            function() {
                if ($this->rack->chains) {
                    // Use stored chain data if available
                    $chains = is_array($this->rack->chains) 
                        ? $this->rack->chains 
                        : json_decode($this->rack->chains, true);
                    
                    return [
                        'chains' => $chains,
                        'type' => $this->rack->rack_type
                    ];
                } elseif ($this->rack->devices) {
                    // Fall back to device data grouped into a single chain
                    $devices = is_array($this->rack->devices)
                        ? $this->rack->devices
                        : json_decode($this->rack->devices, true);
                        
                    return [
                        'chains' => $this->groupDevicesIntoChains($devices),
                        'type' => $this->rack->rack_type
                    ];
                } else {
                    // Try to re-analyze from file
                    return $this->reAnalyzeRackStructure();
                }
            }
        );
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
                    $rackData = [
                        'chains' => $analysis['chains'],
                        'type' => $this->rack->rack_type
                    ];
                    
                    // Update the rack with analyzed data for future use
                    $this->rack->update([
                        'chains' => json_encode($analysis['chains']),
                        'devices' => json_encode($this->flattenDevices($analysis['chains']))
                    ]);
                    
                    return $rackData;
                } else {
                    return [
                        'chains' => [],
                        'type' => $this->rack->rack_type
                    ];
                }
            } catch (\Exception $e) {
                return [
                    'chains' => [],
                    'type' => $this->rack->rack_type
                ];
            }
        } else {
            return [
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
