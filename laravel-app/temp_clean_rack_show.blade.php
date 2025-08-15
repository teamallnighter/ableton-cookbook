<div class="max-w-6xl mx-auto px-6 py-8">
    <!-- Breadcrumb -->
    <div class="mb-8">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-3">
                <li class="inline-flex items-center">
                    <a href="{{ route('home') }}" class="text-primary-cyan hover:text-primary-yellow transition-colors">
                        Home
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <span class="text-neutral-dark">/</span>
                        <span class="ml-3 text-sm font-medium text-black">{{ $rack->title }}</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    <!-- Rack Header -->
    <div class="card mb-12">
        <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-6">
            <!-- Main Info -->
            <div class="flex-1">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h1 class="text-4xl font-bold mb-4 text-black">{{ $rack->title }}</h1>
                        <p class="text-lg text-neutral-dark">
                            by 
                            <a 
                                href="{{ route('users.show', $rack->user) }}" 
                                class="text-primary-cyan hover:text-primary-yellow transition-colors"
                            >
                                {{ $rack->user->name }}
                            </a>
                        </p>
                    </div>
                    
                    <div class="flex items-center gap-3 ml-4">
                        <!-- Edition Badge -->
                        @if($rack->ableton_edition)
                            <span class="text-sm px-4 py-2 rounded-full font-medium {{ $rack->ableton_edition === 'suite' ? 'bg-primary-green text-black' : ($rack->ableton_edition === 'standard' ? 'bg-primary-cyan text-black' : 'bg-primary-yellow text-black') }}">
                                Live {{ ucfirst($rack->ableton_edition) }}
                            </span>
                        @endif
                        
                        <!-- Rating Display -->
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-primary-yellow" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                            <span class="font-semibold text-black">{{ number_format($rack->average_rating, 1) }}</span>
                            <span class="text-sm text-neutral-dark">({{ $rack->ratings_count }} {{ Str::plural('rating', $rack->ratings_count) }})</span>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div class="mb-8">
                    <p class="text-lg leading-relaxed text-neutral-dark">{{ $rack->description }}</p>
                </div>

                <!-- Tags -->
                <div class="flex flex-wrap gap-3 mb-8">
                    @foreach($rack->tags as $tag)
                        <span class="inline-block text-sm px-4 py-2 rounded-full bg-primary-cyan text-black font-medium">
                            {{ $tag->name }}
                        </span>
                    @endforeach
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
                    <div>
                        <div class="text-3xl font-bold text-primary-cyan mb-1">{{ number_format($rack->downloads_count) }}</div>
                        <div class="text-sm text-neutral-dark">Downloads</div>
                    </div>
                    <div>
                        <div class="text-3xl font-bold text-primary-cyan mb-1">{{ number_format($rack->views_count) }}</div>
                        <div class="text-sm text-neutral-dark">Views</div>
                    </div>
                    <div>
                        <div class="text-3xl font-bold text-primary-cyan mb-1">{{ $rack->device_count }}</div>
                        <div class="text-sm text-neutral-dark">Devices</div>
                    </div>
                    <div>
                        <div class="text-3xl font-bold text-primary-cyan mb-1">{{ $rack->chain_count }}</div>
                        <div class="text-sm text-neutral-dark">Chains</div>
                    </div>
                </div>

                <!-- User Rating Section -->
                @auth
                    <div class="mt-8 p-6 card">
                        <h3 class="font-semibold mb-4 text-black">
                            @if($userRating > 0)
                                Your Rating: {{ $userRating }} {{ Str::plural('star', $userRating) }}
                            @else
                                Rate this rack
                            @endif
                        </h3>
                        
                        <div class="flex items-center gap-2">
                            @for($i = 1; $i <= 5; $i++)
                                <button
                                    wire:click="rateRack({{ $i }})"
                                    wire:mouseover="setHoveredStar({{ $i }})"
                                    wire:mouseleave="clearHoveredStar"
                                    class="text-primary-yellow transition-all duration-150 hover:scale-110 transform"
                                >
                                    <svg 
                                        class="w-8 h-8" 
                                        fill="{{ ($hoveredStar > 0 ? $hoveredStar : $userRating) >= $i ? 'currentColor' : 'none' }}"
                                        stroke="currentColor" 
                                        viewBox="0 0 24 24"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                    </svg>
                                </button>
                            @endfor
                        </div>
                        
                        @if($userRating > 0)
                            <p class="text-sm mt-3 text-neutral-dark">
                                Click a star to change your rating
                            </p>
                        @else
                            <p class="text-sm mt-3 text-neutral-dark">
                                Click a star to rate this rack
                            </p>
                        @endif
                    </div>
                @else
                    <div class="mt-8 p-6 card text-center">
                        <p class="text-black">
                            <a href="{{ route('login') }}" class="text-primary-cyan hover:text-primary-yellow transition-colors">Login</a> 
                            or 
                            <a href="{{ route('register') }}" class="text-primary-cyan hover:text-primary-yellow transition-colors">Register</a> 
                            to rate this rack
                        </p>
                    </div>
                @endauth
            </div>

            <!-- Download Action -->
            <div class="flex flex-col gap-4">
                <button 
                    wire:click="downloadRack"
                    class="btn-primary text-lg flex items-center gap-3 justify-center"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path>
                    </svg>
                    Download Rack
                </button>

                @auth
                    <button 
                        wire:click="toggleFavorite"
                        class="{{ $isFavorited ? 'btn-danger' : 'btn-secondary' }} text-lg flex items-center gap-3 justify-center"
                    >
                        <svg class="w-5 h-5" fill="{{ $isFavorited ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                        {{ $isFavorited ? 'Remove from Favorites' : 'Add to Favorites' }}
                    </button>
                    
                    @if(auth()->id() === $rack->user_id)
                        <a 
                            href="{{ route('racks.edit', $rack) }}"
                            class="btn-secondary text-lg flex items-center gap-3 justify-center"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Edit Rack
                        </a>
                        
                        <button 
                            wire:click="deleteRack"
                            wire:confirm="Are you sure you want to delete this rack? This action cannot be undone."
                            class="btn-danger text-lg flex items-center gap-3 justify-center"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Delete Rack
                        </button>
                    @else
                        <!-- Report button for non-owners -->
                        <button 
                            wire:click="openReportModal"
                            class="btn-danger text-lg flex items-center gap-3 justify-center"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                            Report Issue
                        </button>
                    @endif
                @else
                    <div class="card text-center">
                        <p class="text-sm text-black">
                            <a href="{{ route('login') }}" class="text-primary-cyan hover:text-primary-yellow transition-colors">Login</a> 
                            to favorite this rack
                        </p>
                    </div>
                @endauth
                
                <div class="text-sm text-center text-neutral-dark mt-4">
                    {{ number_format($rack->file_size / 1024, 1) }} KB
                </div>
            </div>
        </div>
    </div>

    <!-- Technical Details -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
        <!-- Rack Structure Tree View -->
        <div class="lg:col-span-2">
            <div class="card">
                <h2 class="text-2xl font-bold mb-8 flex items-center gap-3 text-black">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                    </svg>
                    Device Chain
                </h2>
                
                <!-- Chain View Container -->
                <div class="" x-data="{ expandedChains: {}, expandAll: false }">
                    <!-- Expand/Collapse All Button -->
                    <div class="flex justify-end mb-6">
                        <button 
                            @click="expandAll = !expandAll; Object.keys(expandedChains).forEach(key => expandedChains[key] = expandAll)"
                            class="btn-secondary text-sm"
                        >
                            <span x-text="expandAll ? 'Collapse All' : 'Expand All'"></span>
                        </button>
                    </div>

                    <!-- Root Rack Node -->
                    <div class="mb-8">
                        <!-- Root Node -->
                        <div class="bg-black rounded-lg p-6 border border-primary-yellow">
                            <div class="flex items-center gap-4">
                                <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-primary-yellow">
                                    <svg class="w-6 h-6 text-black" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <span class="font-bold text-white text-lg">{{ $rack->title }}</span>
                                        <span class="text-xs px-3 py-1 rounded-full bg-neutral-dark text-white">
                                            {{ $rackData['type'] === 'AudioEffectGroupDevice' ? 'Audio Effect Rack' : ($rackData['type'] === 'InstrumentGroupDevice' ? 'Instrument Rack' : 'MIDI Effect Rack') }}
                                        </span>
                                    </div>
                                    @if(!empty($rackData['chains']))
                                        <div class="text-sm text-neutral-medium">
                                            {{ count($rackData['chains']) }} {{ Str::plural('chain', count($rackData['chains'])) }} • 
                                            {{ collect($rackData['chains'])->sum(fn($chain) => count($chain['devices'])) }} total devices
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Clean Chain Layout -->
                        @if(!empty($rackData['chains']))
                            <div class="space-y-6 mt-8">
                                @foreach($rackData['chains'] as $chainIndex => $chain)
                                    <div class="border border-neutral-dark rounded-lg overflow-hidden" x-data="{ expanded: false, init() { this.$watch('expandAll', value => this.expanded = value) } }">
                                        <!-- Chain Header -->
                                        <div 
                                            class="bg-primary-cyan bg-opacity-10 p-4 cursor-pointer hover:bg-opacity-20 transition-all"
                                            @click="expanded = !expanded"
                                        >
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-4">
                                                    <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-primary-cyan">
                                                        <svg class="w-4 h-4 text-black" fill="currentColor" viewBox="0 0 24 24">
                                                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                                                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <h3 class="font-semibold text-black">
                                                            @if(isset($rack->chain_annotations[$chainIndex]['custom_name']) && !empty($rack->chain_annotations[$chainIndex]['custom_name']))
                                                                {{ $rack->chain_annotations[$chainIndex]['custom_name'] }}
                                                                <span class="text-sm font-normal text-neutral-dark ml-2">(Chain {{ $chainIndex + 1 }})</span>
                                                            @else
                                                                Chain {{ $chainIndex + 1 }}
                                                            @endif
                                                        </h3>
                                                        <p class="text-sm text-neutral-dark">
                                                            {{ count($chain['devices']) }} {{ Str::plural('device', count($chain['devices'])) }}
                                                        </p>
                                                    </div>
                                                </div>
                                                
                                                <!-- Expand Icon -->
                                                <svg 
                                                    class="w-5 h-5 text-primary-cyan transform transition-transform duration-200" 
                                                    :class="expanded ? 'rotate-90' : ''"
                                                    fill="none" 
                                                    stroke="currentColor" 
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                                </svg>
                                            </div>
                                            
                                            <!-- Chain Annotation Note -->
                                            @if(isset($rack->chain_annotations[$chainIndex]['note']) && !empty($rack->chain_annotations[$chainIndex]['note']))
                                                <div class="mt-4 p-3 bg-primary-cyan bg-opacity-20 rounded-lg">
                                                    <div class="text-sm text-black">
                                                        <span class="font-medium text-primary-cyan">{{ $rack->user->name }} says:</span> 
                                                        {{ $rack->chain_annotations[$chainIndex]['note'] }}
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                        
                                        <!-- Devices in Chain -->
                                        <div x-show="expanded" x-collapse class="bg-white">
                                            @if(!empty($chain['devices']))
                                                <!-- Device Flow (horizontal layout like Ableton) -->
                                                <div class="p-6">
                                                    <div class="flex flex-wrap gap-4">
                                                        @foreach($chain['devices'] as $deviceIndex => $device)
                                                            <div class="flex-shrink-0">
                                                                <!-- Device Block -->
                                                                <div class="bg-neutral-light border border-neutral-dark rounded-lg p-4 min-w-[140px] text-center hover:border-primary-cyan transition-colors">
                                                                    <!-- Device Icon -->
                                                                    <div class="flex justify-center mb-3">
                                                                        @if(isset($device['chains']) && !empty($device['chains']))
                                                                            <!-- Nested rack icon -->
                                                                            <div class="w-8 h-8 rounded-lg bg-primary-yellow flex items-center justify-center">
                                                                                <svg class="w-5 h-5 text-black" fill="currentColor" viewBox="0 0 24 24">
                                                                                    <path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                                                                </svg>
                                                                            </div>
                                                                        @else
                                                                            <!-- Regular device icon -->
                                                                            <div class="w-8 h-8 rounded-lg bg-primary-green flex items-center justify-center">
                                                                                <div class="w-3 h-3 rounded-full bg-black"></div>
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                    
                                                                    <!-- Device Name -->
                                                                    <div class="text-sm font-medium text-black mb-1">
                                                                        {{ $device['name'] ?? 'Unknown Device' }}
                                                                    </div>
                                                                    
                                                                    <!-- Device Info -->
                                                                    <div class="space-y-1">
                                                                        @if(isset($device['preset']) && $device['preset'])
                                                                            <div class="text-xs px-2 py-1 rounded-full bg-danger text-black">
                                                                                {{ $device['preset'] }}
                                                                            </div>
                                                                        @endif
                                                                        @if(isset($device['chains']) && !empty($device['chains']))
                                                                            <div class="text-xs px-2 py-1 rounded-full bg-primary-cyan text-black">
                                                                                {{ count($device['chains']) }} nested
                                                                            </div>
                                                                        @endif
                                                                        @if(isset($device['type']))
                                                                            <div class="text-xs text-neutral-dark">
                                                                                {{ $device['type'] }}
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                                
                                                                <!-- Connection Arrow (except for last device) -->
                                                                @if($deviceIndex < count($chain['devices']) - 1)
                                                                    <div class="flex justify-center mt-2">
                                                                        <svg class="w-6 h-6 text-neutral-dark" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                                                        </svg>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    
                                                    <!-- Nested devices would be handled separately if needed -->
                                                    @foreach($chain['devices'] as $deviceIndex => $device)
                                                        @if(isset($device['chains']) && !empty($device['chains']))
                                                            <div class="mt-6 pt-6 border-t border-neutral-light">
                                                                <h4 class="text-sm font-semibold text-black mb-4">
                                                                    Nested in {{ $device['name'] ?? 'Unknown Device' }}:
                                                                </h4>
                                                                <!-- Simplified nested view -->
                                                                <div class="pl-6 space-y-2">
                                                                    @foreach($device['chains'] as $nestedChain)
                                                                        <div class="text-sm text-neutral-dark">
                                                                            <span class="font-medium">Chain:</span>
                                                                            @foreach($nestedChain['devices'] ?? [] as $nestedDevice)
                                                                                <span class="inline-block ml-2 px-2 py-1 bg-neutral-light rounded text-xs">
                                                                                    {{ $nestedDevice['name'] ?? 'Unknown' }}
                                                                                </span>
                                                                            @endforeach
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @else
                                                <div class="p-6 text-center text-neutral-dark text-sm">
                                                    No devices in this chain
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Empty State -->
                @if(empty($rackData['chains']))
                    <div class="text-center py-12 text-neutral-dark">
                        <svg class="w-12 h-12 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                        <p class="text-sm">No device structure available</p>
                        <p class="text-xs mt-1">This rack may not have been fully analyzed</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Metadata Sidebar -->
        <div class="space-y-6">
            <!-- Technical Info -->
            <div class="card">
                <h3 class="font-bold mb-4 text-black">Technical Details</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-neutral-dark">Ableton Version:</span>
                        <span class="text-black font-medium">{{ $rack->ableton_version ?: 'Unknown' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-neutral-dark">File Size:</span>
                        <span class="text-black font-medium">{{ number_format($rack->file_size / 1024, 1) }} KB</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-neutral-dark">Published:</span>
                        <span class="text-black font-medium">
                            {{ $rack->published_at ? $rack->published_at->format('M d, Y') : 'Pending' }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-neutral-dark">Status:</span>
                        <span class="text-xs px-3 py-1 rounded-full bg-primary-green text-black font-medium">
                            {{ ucfirst($rack->status) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card">
                <h3 class="font-bold mb-4 text-black">Activity</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-neutral-dark">Last viewed:</span>
                        <span class="text-black font-medium">{{ $rack->updated_at->diffForHumans() }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-neutral-dark">Comments:</span>
                        <span class="text-black font-medium">{{ $rack->comments_count }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-neutral-dark">Likes:</span>
                        <span class="text-black font-medium">{{ $rack->likes_count }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Modal -->
    @if($showReportModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-75">
            <div class="max-w-md w-full card">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-black">Report Issue</h3>
                    <button wire:click="closeReportModal" class="text-neutral-dark hover:text-black">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form wire:submit.prevent="submitReport">
                    <!-- Issue Type -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2 text-black">
                            What's wrong with this rack?
                        </label>
                        <select 
                            wire:model="reportIssueType" 
                            class="input-field"
                        >
                            <option value="">Select an issue...</option>
                            @foreach(\App\Models\RackReport::getIssueTypes() as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('reportIssueType') 
                            <span class="text-sm mt-1 block text-danger">{{ $message }}</span> 
                        @enderror
                    </div>

                    <!-- Description -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium mb-2 text-black">
                            Please describe the issue
                        </label>
                        <textarea 
                            wire:model="reportDescription" 
                            rows="4"
                            placeholder="Please provide details about the problem you encountered..."
                            class="input-field"
                        ></textarea>
                        @error('reportDescription') 
                            <span class="text-sm mt-1 block text-danger">{{ $message }}</span> 
                        @enderror
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-3">
                        <button 
                            type="button"
                            wire:click="closeReportModal"
                            class="btn-secondary flex-1"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit"
                            class="btn-danger flex-1"
                        >
                            Submit Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Flash Messages -->
    @if(session()->has('success'))
        <div class="card bg-primary-green text-black mb-4">
            {{ session('success') }}
        </div>
    @endif
    
    @if(session()->has('error'))
        <div class="card bg-danger text-black mb-4">
            {{ session('error') }}
        </div>
    @endif
</div>