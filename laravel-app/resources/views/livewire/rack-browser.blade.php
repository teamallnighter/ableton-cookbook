<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold" style="color: #0D0D0D;">🎵 Ableton Cookbook</h1>
        <p class="mt-2" style="color: #6C6C6C;">Discover and share amazing Ableton Live racks</p>
    </div>

    <!-- Search and Filters -->
    <div class="rounded-lg shadow-sm border p-6 mb-8" style="background-color: #0D0D0D; border-color: #4a4a4a;" x-data="{ filtersOpen: false }">
        <div class="flex flex-col lg:flex-row gap-4 items-center">
            <!-- Search -->
            <div class="flex-1 min-w-0">
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search racks..." 
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:outline-none"
                    style="background-color: #4a4a4a; border-color: #6C6C6C; color: #BBBBBB;"
                >
            </div>

            <!-- Sort -->
            <select 
                wire:model.live="sortBy" 
                class="px-4 py-2 border rounded-lg focus:ring-2 focus:outline-none"
                style="background-color: #4a4a4a; border-color: #6C6C6C; color: #BBBBBB;"
            >
                @foreach($sortOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>

            <!-- Filters Toggle -->
            <button 
                @click="filtersOpen = !filtersOpen"
                class="px-4 py-2 rounded-lg hover:opacity-90 transition-opacity flex items-center gap-2"
                style="background-color: #01CADA; color: #0D0D0D;"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                </svg>
                Filters
            </button>
        </div>

        <!-- Advanced Filters -->
        <div x-show="filtersOpen" x-collapse class="mt-4 pt-4 border-t" style="border-color: #6C6C6C;">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Rack Type Filter -->
                <div>
                    <label class="block text-sm font-medium mb-2" style="color: #BBBBBB;">Type</label>
                    <select wire:model.live="selectedRackType" class="w-full px-3 py-2 border rounded-md focus:ring-2 focus:outline-none" style="background-color: #4a4a4a; border-color: #6C6C6C; color: #BBBBBB;">
                        <option value="">All Types</option>
                        @foreach($rackTypes as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Category Filter -->
                <div>
                    <label class="block text-sm font-medium mb-2" style="color: #BBBBBB;">Category</label>
                    <select wire:model.live="selectedCategory" class="w-full px-3 py-2 border rounded-md focus:ring-2 focus:outline-none" style="background-color: #4a4a4a; border-color: #6C6C6C; color: #BBBBBB;">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category }}">{{ $category }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Clear Filters -->
                <div class="flex items-end">
                    <button 
                        wire:click="clearFilters"
                        class="w-full px-4 py-2 rounded-md hover:opacity-90 transition-opacity"
                        style="background-color: #F87680; color: #0D0D0D;"
                    >
                        Clear Filters
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Summary -->
    <div class="flex justify-between items-center mb-6">
        <p style="color: #6C6C6C;">
            {{ $racks->total() }} racks found
            @if($search)
                for "{{ $search }}"
            @endif
        </p>
        
        <!-- Loading indicator -->
        <div wire:loading class="flex items-center" style="color: #01CADA;">
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Loading...
        </div>
    </div>

    <!-- Racks Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8">
        @forelse($racks as $rack)
            <div 
                onclick="window.location.href='{{ route('racks.show', $rack) }}'"
                class="rounded-lg shadow-sm border overflow-hidden hover:shadow-md transition-shadow cursor-pointer h-full flex flex-col"
                style="background-color: #4a4a4a; border-color: #6C6C6C;"
            >
                <!-- Rack Header -->
                <div class="p-4 border-b" style="border-color: #6C6C6C;">
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <h3 class="font-semibold truncate" style="color: #BBBBBB;">
                                {{ $rack->title }}
                            </h3>
                            <p class="text-sm mt-1" style="color: #6C6C6C;">
                                by 
                                <a 
                                    href="{{ route('users.show', $rack->user) }}" 
                                    onclick="event.stopPropagation();"
                                    style="color: #01CADA;" 
                                    class="hover:opacity-80 transition-opacity"
                                >
                                    {{ $rack->user->name }}
                                </a>
                            </p>
                        </div>
                        
                        <div class="flex items-center gap-2 ml-2">
                            <!-- Favorite Heart (for authenticated users) -->
                            @auth
                                <button 
                                    wire:click="toggleFavorite({{ $rack->id }})"
                                    onclick="event.stopPropagation();"
                                    class="hover:scale-110 transition-transform"
                                    title="{{ $rack->is_favorited_by_user ? 'Remove from favorites' : 'Add to favorites' }}"
                                >
                                    <svg class="w-4 h-4" fill="{{ $rack->is_favorited_by_user ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24" style="color: #F87680;">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                    </svg>
                                </button>
                            @endauth
                            
                            <!-- Rating -->
                            <div class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" style="color: #ffdf00;">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                </svg>
                                <span class="text-sm" style="color: #BBBBBB;">{{ number_format($rack->average_rating, 1) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rack Content -->
                <div class="p-4 flex-1 flex flex-col">
                    <!-- Description -->
                    <p class="text-sm mb-4 line-clamp-2 flex-1" style="color: #BBBBBB;">{{ $rack->description }}</p>
                    
                    <!-- Bottom Info -->
                    <div class="mt-auto">
                        <!-- Rack Info Row -->
                        <div class="flex items-center justify-between text-xs">
                            <div class="flex items-center gap-2">
                                <!-- Rack Type -->
                                <span class="px-2 py-1 rounded" style="background-color: #6C6C6C; color: #BBBBBB;">
                                    {{ $rackTypes[$rack->rack_type] ?? $rack->rack_type }}
                                </span>
                                
                                <!-- Category -->
                                @if($rack->category)
                                    <span class="px-2 py-1 rounded font-medium" style="background-color: #01CADA; color: #0D0D0D;">
                                        {{ $rack->category }}
                                    </span>
                                @endif
                                
                                <!-- Ableton Edition -->
                                @if($rack->ableton_edition)
                                    <span class="px-2 py-1 rounded font-medium" 
                                          style="background-color: {{ $rack->ableton_edition === 'suite' ? '#01DA48' : ($rack->ableton_edition === 'standard' ? '#01CADA' : '#ffdf00') }}; color: #0D0D0D;">
                                        {{ ucfirst($rack->ableton_edition) }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-12">
                <div class="text-6xl mb-4" style="color: #6C6C6C;">🎛️</div>
                <h3 class="text-lg font-medium mb-2" style="color: #BBBBBB;">No racks found</h3>
                <p style="color: #6C6C6C;">Try adjusting your search or filters</p>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($racks->hasPages())
        <div class="flex justify-center">
            {{ $racks->links() }}
        </div>
    @endif
</div>