<?php

namespace App\Livewire;

use App\Models\User;
use App\Models\Rack;
use App\Models\RackFavorite;
use Livewire\Component;
use Livewire\WithPagination;

class UserProfile extends Component
{
    use WithPagination;

    public User $user;
    public $activeTab = 'uploads';
    public $isOwnProfile = false;

    public function mount(User $user)
    {
        $this->user = $user;
        $this->isOwnProfile = auth()->check() && auth()->id() === $user->id;
        
        // Default to favorites if viewing own profile, uploads for others
        $this->activeTab = $this->isOwnProfile ? 'favorites' : 'uploads';
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
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
        $racks = collect();
        
        if ($this->activeTab === 'uploads') {
            $racks = Rack::where('user_id', $this->user->id)
                ->with(['user:id,name', 'tags'])
                ->published()
                ->orderBy('created_at', 'desc')
                ->paginate(12);
        } elseif ($this->activeTab === 'favorites') {
            // Only show favorites for own profile or if user wants them public
            if ($this->isOwnProfile) {
                $racks = Rack::whereHas('favorites', function ($query) {
                    $query->where('user_id', $this->user->id);
                })
                ->with(['user:id,name', 'tags'])
                ->published()
                ->orderBy('rack_favorites.created_at', 'desc')
                ->paginate(12);
            }
        }

        // Add favorite status for authenticated users
        if (auth()->check() && $racks->count() > 0) {
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

        // Get user stats
        $stats = [
            'total_uploads' => Rack::where('user_id', $this->user->id)->published()->count(),
            'total_downloads' => Rack::where('user_id', $this->user->id)->published()->sum('downloads_count'),
            'total_views' => Rack::where('user_id', $this->user->id)->published()->sum('views_count'),
            'total_favorites' => RackFavorite::whereHas('rack', function ($query) {
                $query->where('user_id', $this->user->id);
            })->count(),
            'average_rating' => Rack::where('user_id', $this->user->id)->published()->avg('average_rating') ?: 0,
        ];

        return view('livewire.user-profile', [
            'racks' => $racks,
            'stats' => $stats
        ]);
    }
}
