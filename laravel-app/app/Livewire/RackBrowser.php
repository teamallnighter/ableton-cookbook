<?php

namespace App\Livewire;

use App\Models\Rack;
use App\Models\RackFavorite;
use Livewire\Component;
use Livewire\WithPagination;

class RackBrowser extends Component
{
    use WithPagination;

    public $search = '';
    public $selectedRackType = '';
    public $selectedCategory = '';
    public $sortBy = 'created_at';
    public $sortDirection = 'desc';
    public $perPage = 12;

    public $rackTypes = [
        'AudioEffectGroupDevice' => 'Audio Effects',
        'InstrumentGroupDevice' => 'Instruments', 
        'MidiEffectGroupDevice' => 'MIDI Effects'
    ];

    public $sortOptions = [
        'created_at' => 'Newest',
        'downloads_count' => 'Most Downloaded',
        'average_rating' => 'Highest Rated',
        'views_count' => 'Most Viewed'
    ];

    public function updating($field)
    {
        if (in_array($field, ['search', 'selectedRackType', 'selectedCategory', 'sortBy', 'sortDirection'])) {
            $this->resetPage();
        }
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->selectedRackType = '';
        $this->selectedCategory = '';
        $this->sortBy = 'created_at';
        $this->sortDirection = 'desc';
        $this->resetPage();
    }

    public function toggleFavorite($rackId)
    {
        if (!auth()->check()) {
            session()->flash('error', 'You must be logged in to favorite racks.');
            return;
        }

        $favorite = RackFavorite::where('rack_id', $rackId)
            ->where('user_id', auth()->id())
            ->first();

        if ($favorite) {
            $favorite->delete();
            session()->flash('success', 'Removed from favorites!');
        } else {
            RackFavorite::create([
                'rack_id' => $rackId,
                'user_id' => auth()->id()
            ]);
            session()->flash('success', 'Added to favorites!');
        }
    }

    public function render()
    {
        $query = Rack::query()
            ->with(['user:id,name,profile_photo_path'])
            ->published();

        // Search
        if ($this->search) {
            $query->where('title', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
        }

        // Filter by rack type
        if ($this->selectedRackType) {
            $query->where('rack_type', $this->selectedRackType);
        }

        // Filter by category
        if ($this->selectedCategory) {
            $query->where('category', $this->selectedCategory);
        }

        // Sorting
        if ($this->sortDirection === 'desc') {
            $query->orderByDesc($this->sortBy);
        } else {
            $query->orderBy($this->sortBy);
        }

        $racks = $query->paginate($this->perPage);
        
        // Add favorite status for authenticated users
        if (auth()->check()) {
            $favoriteRackIds = RackFavorite::where('user_id', auth()->id())
                ->whereIn('rack_id', $racks->pluck('id'))
                ->pluck('rack_id')
                ->toArray();
                
            $racks->getCollection()->transform(function ($rack) use ($favoriteRackIds) {
                $rack->is_favorited_by_user = in_array($rack->id, $favoriteRackIds);
                return $rack;
            });
        } else {
            $racks->getCollection()->transform(function ($rack) {
                $rack->is_favorited_by_user = false;
                return $rack;
            });
        }
        
        // Get available categories for filter dropdown
        $categories = Rack::whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values()
            ->toArray();

        return view('livewire.rack-browser', [
            'racks' => $racks,
            'categories' => $categories
        ]);
    }
}
