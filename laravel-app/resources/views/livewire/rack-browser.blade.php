<div class="max-w-7xl mx-auto px-6 py-8">
    <!-- Header -->
    <div class="text-center mb-12">
        <h1 class="text-4xl font-bold text-black mb-4">Discover Racks</h1>
        <p class="text-gray-700">Share and explore amazing Ableton Live racks from the community</p>
    </div>

    <!-- Search and Filters -->
    <div class="card card-body mb-12" x-data="{ filtersOpen: false }">
        <div class="flex flex-col lg:flex-row gap-6 items-center">
            <!-- Search -->
            <div class="flex-1">
                <input 
                    type="text" 
                    wire:model="search"
                    wire:keyup.debounce.300ms="$refresh"
                    placeholder="Search racks..." 
                    class="input-field text-lg"
                >
            </div>

            <!-- Sort -->
            <select 
                wire:model="sortBy" 
                wire:change="$refresh"
                class="input-field min-w-[160px]"
            >
                @foreach($sortOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>

            <!-- Filters Toggle -->
            <button 
                @click="filtersOpen = !filtersOpen"
                class="btn-secondary flex items-center gap-2"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                </svg>
                Filters
            </button>
        </div>

        <!-- Advanced Filters -->
        <div x-show="filtersOpen" x-collapse class="mt-8 pt-8 border-t-2 border-black">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <!-- Rack Type Filter -->
                <div>
                    <label class="block text-sm font-medium mb-3 text-black">Type</label>
                    <select wire:model="selectedRackType" wire:change="$refresh" class="input-field">
                        <option value="">All Types</option>
                        @foreach($rackTypes as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Category Filter -->
                <div>
                    <label class="block text-sm font-medium mb-3 text-black">Category</label>
                    <select wire:model="selectedCategory" wire:change="$refresh" class="input-field">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category }}">{{ $category }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Ableton Edition Filter -->
                <div>
                    <label class="block text-sm font-medium mb-3 text-black">Your Edition</label>
                    <select wire:model="selectedEdition" wire:change="$refresh" class="input-field">
                        <option value="">All Editions</option>
                        <option value="intro" title="Shows only racks that work with Live Intro">Live Intro</option>
                        <option value="standard" title="Shows racks that work with Live Standard (includes Intro racks)">Live Standard</option>
                        <option value="suite" title="Shows all racks">Live Suite</option>
                    </select>
                    @if($selectedEdition)
                        <p class="text-xs mt-2 text-gray-600">
                            @if($selectedEdition === 'intro')
                                Shows Intro-compatible racks only
                            @elseif($selectedEdition === 'standard')
                                Shows Intro & Standard racks
                            @else
                                Shows all racks
                            @endif
                        </p>
                    @endif
                </div>

                <!-- Clear Filters -->
                <div class="flex flex-col justify-end gap-3">
                    <button wire:click="clearFilters" class="btn-secondary">
                        Clear Filters
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Summary -->
    <div class="flex justify-between items-center mb-8">
        <p class="text-gray-700 text-lg">
            <span class="font-bold text-black">{{ $racks->total() }}</span> racks found
            @if($search)
                for "{{ $search }}"
            @endif
        </p>
        
        <!-- Loading indicator -->
        <div wire:loading class="flex items-center text-black">
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Loading...
        </div>
    </div>

    <!-- Racks Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8 mb-12">
        @forelse($racks as $rack)
            <div 
                onclick="window.location.href='{{ route('racks.show', $rack) }}'"
                class="card hover:shadow-lg transition-all cursor-pointer h-full flex flex-col group"
            >
                <!-- Rack Header -->
                <div class="p-4 border-b-2 border-black">
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <h3 class="font-bold truncate text-black group-hover:text-vibrant-purple transition-colors">
                                {{ $rack->title }}
                            </h3>
                            <p class="text-sm mt-1 text-gray-600">
                                by 
                                <a 
                                    href="{{ route('users.show', $rack->user) }}" 
                                    onclick="event.stopPropagation();"
                                    class="link"
                                >
                                    {{ $rack->user->name }}
                                </a>
                            </p>
                        </div>
                        
                        <!-- Compact Actions -->
                        <div class="flex items-center gap-1">
                            @auth
                                <button 
                                    wire:click="toggleFavorite({{ $rack->id }})"
                                    onclick="event.stopPropagation();"
                                    class="p-1 hover:scale-110 transition-transform"
                                    title="{{ $rack->is_favorited_by_user ? 'Remove from favorites' : 'Add to favorites' }}"
                                >
                                    <svg class="w-5 h-5 favorite-btn {{ $rack->is_favorited_by_user ? 'active' : '' }}" fill="{{ $rack->is_favorited_by_user ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                                    </svg>
                                </button>
                            @endauth
                            
                            <!-- Rating -->
                            <div class="flex items-center gap-1">
                                <svg class="w-4 h-4 star-btn {{ $rack->average_rating > 0 ? 'active' : '' }}" fill="{{ $rack->average_rating > 0 ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                </svg>
                                <span class="text-sm font-medium text-black">{{ number_format($rack->average_rating, 1) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rack Content -->
                <div class="p-4 flex-1 flex flex-col">
                    <!-- Description -->
                    <p class="text-sm mb-4 line-clamp-2 flex-1 text-gray-700 leading-relaxed">{{ $rack->description }}</p>
                    
                    <!-- Bottom Info -->
                    <div class="mt-auto">
                        <!-- Badges Row -->
                        <div class="flex flex-wrap gap-2">
                            <!-- Ableton Edition -->
                            @if($rack->ableton_edition)
                                <span class="{{ $rack->ableton_edition === 'suite' ? 'edition-suite' : ($rack->ableton_edition === 'standard' ? 'edition-standard' : 'edition-intro') }}">
                                    {{ ucfirst($rack->ableton_edition) }}
                                </span>
                            @endif
                            
                            <!-- Category -->
                            @if($rack->category)
                                <span class="badge-category">
                                    {{ $rack->category }}
                                </span>
                            @endif
                            
                            <!-- Chain Annotations Indicator -->
                            @if($rack->chain_annotations && collect($rack->chain_annotations)->some(fn($annotation) => !empty($annotation['custom_name']) || !empty($annotation['note'])))
                                <span class="badge-warning" title="This rack has educational notes from the creator">
                                    Notes
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-20">
                <div class="text-8xl mb-6 text-ableton-light/30 opacity-50">🎛️</div>
                <h3 class="text-xl font-semibold mb-3 text-ableton-light">No racks found</h3>
                <p class="text-ableton-light/60">Try adjusting your search or filters</p>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($racks->hasPages())
        <div class="flex justify-center mt-12">
            {{ $racks->links() }}
        </div>
    @endif
</div>