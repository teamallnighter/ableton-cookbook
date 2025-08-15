<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Breadcrumb -->
    <div class="mb-6">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="{{ route('home') }}" style="color: #01CADA;" class="hover:opacity-80">
                        🏠 Home
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <span style="color: #6C6C6C;">/</span>
                        <span class="ml-1 md:ml-2 text-sm font-medium" style="color: #0D0D0D;">{{ $rack->title }}</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    <!-- Rack Header -->
    <div class="rounded-lg p-6 mb-8" style="background-color: #0D0D0D; border: 1px solid #4a4a4a;">
        <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-6">
            <!-- Main Info -->
            <div class="flex-1">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h1 class="text-3xl font-bold mb-2" style="color: #BBBBBB;">{{ $rack->title }}</h1>
                        <p class="text-lg" style="color: #6C6C6C;">
                            by 
                            <a 
                                href="{{ route('users.show', $rack->user) }}" 
                                style="color: #01CADA;" 
                                class="hover:opacity-80 transition-opacity"
                            >
                                {{ $rack->user->name }}
                            </a>
                        </p>
                    </div>
                    
                    <div class="flex items-center gap-3 ml-4">
                        <!-- Edition Badge -->
                        @if($rack->ableton_edition)
                            <span class="text-sm px-3 py-1 rounded-full font-medium" 
                                  style="background-color: {{ $rack->ableton_edition === 'suite' ? '#01DA48' : ($rack->ableton_edition === 'standard' ? '#01CADA' : '#ffdf00') }}; color: #0D0D0D;">
                                Live {{ ucfirst($rack->ableton_edition) }}
                            </span>
                        @endif
                        
                        <!-- Rating Display -->
                        <div class="flex items-center gap-1">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" style="color: #ffdf00;">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                            <span class="font-semibold" style="color: #BBBBBB;">{{ number_format($rack->average_rating, 1) }}</span>
                            <span class="text-sm" style="color: #6C6C6C;">({{ $rack->ratings_count }} {{ Str::plural('rating', $rack->ratings_count) }})</span>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div class="mb-6">
                    <p class="text-base leading-relaxed" style="color: #BBBBBB;">{{ $rack->description }}</p>
                </div>

                <!-- Tags -->
                <div class="flex flex-wrap gap-2 mb-6">
                    @foreach($rack->tags as $tag)
                        <span class="inline-block text-sm px-3 py-1 rounded-full" style="background-color: #01CADA; color: #0D0D0D;">
                            {{ $tag->name }}
                        </span>
                    @endforeach
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                    <div>
                        <div class="text-2xl font-bold" style="color: #01CADA;">{{ number_format($rack->downloads_count) }}</div>
                        <div class="text-sm" style="color: #6C6C6C;">Downloads</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold" style="color: #01CADA;">{{ number_format($rack->views_count) }}</div>
                        <div class="text-sm" style="color: #6C6C6C;">Views</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold" style="color: #01CADA;">{{ $rack->device_count }}</div>
                        <div class="text-sm" style="color: #6C6C6C;">Devices</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold" style="color: #01CADA;">{{ $rack->chain_count }}</div>
                        <div class="text-sm" style="color: #6C6C6C;">Chains</div>
                    </div>
                </div>

                <!-- User Rating Section -->
                @auth
                    <div class="mt-6 p-4 rounded-lg" style="background-color: #4a4a4a; border: 1px solid #6C6C6C;">
                        <h3 class="font-semibold mb-3" style="color: #BBBBBB;">
                            @if($userRating > 0)
                                Your Rating: {{ $userRating }} {{ Str::plural('star', $userRating) }}
                            @else
                                Rate this rack
                            @endif
                        </h3>
                        
                        <div class="flex items-center gap-1">
                            @for($i = 1; $i <= 5; $i++)
                                <button
                                    wire:click="rateRack({{ $i }})"
                                    wire:mouseover="setHoveredStar({{ $i }})"
                                    wire:mouseleave="clearHoveredStar"
                                    class="transition-colors duration-150 hover:scale-110 transform"
                                >
                                    <svg 
                                        class="w-8 h-8" 
                                        fill="{{ ($hoveredStar > 0 ? $hoveredStar : $userRating) >= $i ? 'currentColor' : 'none' }}"
                                        stroke="currentColor" 
                                        viewBox="0 0 24 24"
                                        style="color: #ffdf00;"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                    </svg>
                                </button>
                            @endfor
                        </div>
                        
                        @if($userRating > 0)
                            <p class="text-sm mt-2" style="color: #6C6C6C;">
                                Click a star to change your rating
                            </p>
                        @else
                            <p class="text-sm mt-2" style="color: #6C6C6C;">
                                Click a star to rate this rack
                            </p>
                        @endif
                    </div>
                @else
                    <div class="mt-6 p-4 rounded-lg text-center" style="background-color: #4a4a4a; border: 1px solid #6C6C6C;">
                        <p style="color: #BBBBBB;">
                            <a href="{{ route('login') }}" style="color: #01CADA;" class="hover:opacity-80">Login</a> 
                            or 
                            <a href="{{ route('register') }}" style="color: #01CADA;" class="hover:opacity-80">Register</a> 
                            to rate this rack
                        </p>
                    </div>
                @endauth
            </div>

            <!-- Download Action -->
            <div class="flex flex-col gap-4">
                <button 
                    wire:click="downloadRack"
                    class="px-6 py-3 rounded-lg font-semibold text-lg hover:opacity-90 transition-opacity flex items-center gap-2"
                    style="background-color: #01DA48; color: #0D0D0D;"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path>
                    </svg>
                    Download Rack
                </button>

                @auth
                    <button 
                        wire:click="toggleFavorite"
                        class="px-6 py-3 rounded-lg font-semibold text-lg hover:opacity-90 transition-all flex items-center gap-2 justify-center"
                        style="background-color: {{ $isFavorited ? '#F87680' : '#4a4a4a' }}; color: {{ $isFavorited ? '#0D0D0D' : '#BBBBBB' }}; border: 1px solid #6C6C6C;"
                    >
                        <svg class="w-5 h-5" fill="{{ $isFavorited ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                        {{ $isFavorited ? 'Remove from Favorites' : 'Add to Favorites' }}
                    </button>
                    
                    @if(auth()->id() === $rack->user_id)
                        <button 
                            wire:click="deleteRack"
                            wire:confirm="Are you sure you want to delete this rack? This action cannot be undone."
                            class="px-6 py-3 rounded-lg font-semibold text-lg hover:opacity-90 transition-all flex items-center gap-2 justify-center"
                            style="background-color: #F87680; color: #0D0D0D; border: 1px solid #F87680;"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Delete Rack
                        </button>
                    @endif
                @else
                    <div class="px-6 py-3 rounded-lg text-center" style="background-color: #4a4a4a; border: 1px solid #6C6C6C;">
                        <p class="text-sm" style="color: #BBBBBB;">
                            <a href="{{ route('login') }}" style="color: #01CADA;" class="hover:opacity-80">Login</a> 
                            to favorite this rack
                        </p>
                    </div>
                @endauth
                
                <div class="text-sm text-center" style="color: #6C6C6C;">
                    {{ number_format($rack->file_size / 1024, 1) }} KB
                </div>
            </div>
        </div>
    </div>

    <!-- Technical Details -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
        <!-- Rack Structure Tree View -->
        <div class="lg:col-span-2">
            <div class="rounded-lg p-6" style="background-color: #4a4a4a; border: 1px solid #6C6C6C;">
                <h2 class="text-xl font-bold mb-6 flex items-center gap-2" style="color: #BBBBBB;">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                    </svg>
                    Rack Structure
                </h2>
                
                <!-- Tree View Container -->
                <div class="relative" x-data="{ expandedChains: {}, expandAll: false }">
                    <!-- Expand/Collapse All Button -->
                    <div class="absolute -top-12 right-0 flex gap-2">
                        <button 
                            @click="expandAll = !expandAll; Object.keys(expandedChains).forEach(key => expandedChains[key] = expandAll)"
                            class="text-xs px-3 py-1 rounded hover:opacity-80 transition-opacity"
                            style="background-color: #01CADA; color: #0D0D0D;"
                        >
                            <span x-text="expandAll ? 'Collapse All' : 'Expand All'"></span>
                        </button>
                    </div>

                    <!-- Root Rack Node -->
                    <div class="relative">
                        <!-- Root Node -->
                        <div class="flex items-center gap-3 p-3 rounded-lg transition-colors hover:bg-opacity-10 hover:bg-white" style="background-color: #0D0D0D;">
                            <div class="flex items-center justify-center w-8 h-8 rounded" style="background-color: #ffdf00;">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" style="color: #0D0D0D;">
                                    <path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold" style="color: #BBBBBB;">{{ $rack->title }}</span>
                                    <span class="text-xs px-2 py-0.5 rounded-full" style="background-color: #6C6C6C; color: #BBBBBB;">
                                        {{ $rackData['type'] === 'AudioEffectGroupDevice' ? 'Audio Effect Rack' : ($rackData['type'] === 'InstrumentGroupDevice' ? 'Instrument Rack' : 'MIDI Effect Rack') }}
                                    </span>
                                </div>
                                @if(!empty($rackData['chains']))
                                    <span class="text-sm" style="color: #6C6C6C;">
                                        {{ count($rackData['chains']) }} {{ Str::plural('chain', count($rackData['chains'])) }}, 
                                        {{ collect($rackData['chains'])->sum(fn($chain) => count($chain['devices'])) }} total devices
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- Chains Tree -->
                        @if(!empty($rackData['chains']))
                            <div class="ml-4 mt-2">
                                @foreach($rackData['chains'] as $chainIndex => $chain)
                                    <div class="relative" x-data="{ init() { this.$watch('expandAll', value => expandedChains[{{ $chainIndex }}] = value) } }">
                                        <!-- Vertical Line Connector -->
                                        <div class="absolute left-4 top-0 bottom-0 w-px" style="background-color: #6C6C6C;"></div>
                                        
                                        <!-- Horizontal Line Connector -->
                                        <div class="absolute left-4 top-6 w-6 h-px" style="background-color: #6C6C6C;"></div>
                                        
                                        <!-- Chain Node -->
                                        <div class="relative pl-10 pb-2">
                                            <div 
                                                class="flex items-center gap-3 p-2 rounded-lg cursor-pointer transition-all hover:bg-opacity-10 hover:bg-white"
                                                style="background-color: rgba(1, 218, 218, 0.1);"
                                                @click="expandedChains[{{ $chainIndex }}] = !expandedChains[{{ $chainIndex }}]"
                                                x-init="expandedChains[{{ $chainIndex }}] = false"
                                            >
                                                <!-- Expand/Collapse Icon -->
                                                <button class="flex items-center justify-center w-6 h-6 rounded transition-all hover:bg-opacity-20 hover:bg-white">
                                                    <svg 
                                                        class="w-4 h-4 transform transition-transform duration-200" 
                                                        :class="expandedChains[{{ $chainIndex }}] ? 'rotate-90' : ''"
                                                        fill="none" 
                                                        stroke="currentColor" 
                                                        viewBox="0 0 24 24"
                                                        style="color: #01CADA;"
                                                    >
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                                    </svg>
                                                </button>
                                                
                                                <!-- Chain Icon -->
                                                <div class="flex items-center justify-center w-6 h-6 rounded" style="background-color: #01CADA;">
                                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" style="color: #0D0D0D;">
                                                        <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                                                        <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                                                    </svg>
                                                </div>
                                                
                                                <!-- Chain Info -->
                                                <div class="flex-1">
                                                    <span class="font-medium" style="color: #BBBBBB;">
                                                        Chain {{ $chainIndex + 1 }}
                                                    </span>
                                                    <span class="ml-2 text-sm" style="color: #6C6C6C;">
                                                        {{ count($chain['devices']) }} {{ Str::plural('device', count($chain['devices'])) }}
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <!-- Devices in Chain -->
                                            <div 
                                                x-show="expandedChains[{{ $chainIndex }}]" 
                                                x-collapse
                                                class="ml-4 mt-2"
                                            >
                                                @foreach($chain['devices'] as $deviceIndex => $device)
                                                    <div class="relative" x-data="{ expandedDevice: false }">
                                                        <!-- Vertical Line (except for last item) -->
                                                        @if($deviceIndex < count($chain['devices']) - 1)
                                                            <div class="absolute left-5 top-8 bottom-0 w-px" style="background-color: #6C6C6C;"></div>
                                                        @endif
                                                        
                                                        <!-- Horizontal Line -->
                                                        <div class="absolute left-5 top-4 w-6 h-px" style="background-color: #6C6C6C;"></div>
                                                        
                                                        <!-- Device Node -->
                                                        <div class="relative pl-11 pb-2">
                                                            <div 
                                                                class="flex items-center gap-3 p-2 rounded-lg transition-all hover:bg-opacity-10 hover:bg-white {{ isset($device['chains']) && !empty($device['chains']) ? 'cursor-pointer' : '' }}" 
                                                                style="background-color: rgba(13, 13, 13, 0.5);"
                                                                @if(isset($device['chains']) && !empty($device['chains']))
                                                                    @click="expandedDevice = !expandedDevice"
                                                                @endif
                                                            >
                                                                <!-- Expand/Collapse Icon for nested devices -->
                                                                @if(isset($device['chains']) && !empty($device['chains']))
                                                                    <button class="flex items-center justify-center w-5 h-5 rounded transition-all hover:bg-opacity-20 hover:bg-white">
                                                                        <svg 
                                                                            class="w-3 h-3 transform transition-transform duration-200" 
                                                                            :class="expandedDevice ? 'rotate-90' : ''"
                                                                            fill="none" 
                                                                            stroke="currentColor" 
                                                                            viewBox="0 0 24 24"
                                                                            style="color: #01DA48;"
                                                                        >
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                                                        </svg>
                                                                    </button>
                                                                @endif
                                                                
                                                                <!-- Device Icon -->
                                                                <div class="flex items-center justify-center w-6 h-6 rounded-full" style="background-color: {{ isset($device['chains']) && !empty($device['chains']) ? '#ffdf00' : '#01DA48' }};">
                                                                    @if(isset($device['chains']) && !empty($device['chains']))
                                                                        <!-- Nested rack icon -->
                                                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" style="color: #0D0D0D;">
                                                                            <path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                                                        </svg>
                                                                    @else
                                                                        <!-- Regular device icon -->
                                                                        <div class="w-3 h-3 rounded-full" style="background-color: #0D0D0D;"></div>
                                                                    @endif
                                                                </div>
                                                                
                                                                <!-- Device Info -->
                                                                <div class="flex-1 flex items-center gap-2">
                                                                    <span class="text-sm" style="color: #BBBBBB;">
                                                                        {{ $device['name'] ?? 'Unknown Device' }}
                                                                    </span>
                                                                    @if(isset($device['preset']) && $device['preset'])
                                                                        <span class="text-xs px-2 py-0.5 rounded-full" style="background-color: #F87680; color: #0D0D0D;">
                                                                            {{ $device['preset'] }}
                                                                        </span>
                                                                    @endif
                                                                    @if(isset($device['chains']) && !empty($device['chains']))
                                                                        <span class="text-xs px-2 py-0.5 rounded-full" style="background-color: #01CADA; color: #0D0D0D;">
                                                                            {{ count($device['chains']) }} nested {{ Str::plural('chain', count($device['chains'])) }}
                                                                        </span>
                                                                    @endif
                                                                    @if(isset($device['type']))
                                                                        <span class="text-xs" style="color: #6C6C6C;">
                                                                            {{ $device['type'] }}
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                            
                                                            <!-- Nested Chains (if device has chains) -->
                                                            @if(isset($device['chains']) && !empty($device['chains']))
                                                                <div 
                                                                    x-show="expandedDevice" 
                                                                    x-collapse
                                                                    class="ml-6 mt-2"
                                                                >
                                                                    @foreach($device['chains'] as $nestedChainIndex => $nestedChain)
                                                                        <div class="relative" x-data="{ expandedNestedChain: false }">
                                                                            <!-- Vertical Line Connector -->
                                                                            @if($nestedChainIndex < count($device['chains']) - 1)
                                                                                <div class="absolute left-4 top-8 bottom-0 w-px" style="background-color: #6C6C6C;"></div>
                                                                            @endif
                                                                            
                                                                            <!-- Horizontal Line Connector -->
                                                                            <div class="absolute left-4 top-6 w-6 h-px" style="background-color: #6C6C6C;"></div>
                                                                            
                                                                            <!-- Nested Chain Node -->
                                                                            <div class="relative pl-10 pb-2">
                                                                                <div 
                                                                                    class="flex items-center gap-3 p-2 rounded-lg cursor-pointer transition-all hover:bg-opacity-10 hover:bg-white"
                                                                                    style="background-color: rgba(1, 218, 218, 0.05);"
                                                                                    @click="expandedNestedChain = !expandedNestedChain"
                                                                                >
                                                                                    <!-- Expand/Collapse Icon -->
                                                                                    <button class="flex items-center justify-center w-5 h-5 rounded transition-all hover:bg-opacity-20 hover:bg-white">
                                                                                        <svg 
                                                                                            class="w-3 h-3 transform transition-transform duration-200" 
                                                                                            :class="expandedNestedChain ? 'rotate-90' : ''"
                                                                                            fill="none" 
                                                                                            stroke="currentColor" 
                                                                                            viewBox="0 0 24 24"
                                                                                            style="color: #01CADA;"
                                                                                        >
                                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                                                                        </svg>
                                                                                    </button>
                                                                                    
                                                                                    <!-- Nested Chain Icon -->
                                                                                    <div class="flex items-center justify-center w-5 h-5 rounded" style="background-color: #01CADA;">
                                                                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24" style="color: #0D0D0D;">
                                                                                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                                                                                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                                                                                        </svg>
                                                                                    </div>
                                                                                    
                                                                                    <!-- Nested Chain Info -->
                                                                                    <div class="flex-1">
                                                                                        <span class="text-sm font-medium" style="color: #BBBBBB;">
                                                                                            {{ $nestedChain['name'] ?? ('Nested Chain ' . ($nestedChainIndex + 1)) }}
                                                                                        </span>
                                                                                        <span class="ml-2 text-xs" style="color: #6C6C6C;">
                                                                                            {{ count($nestedChain['devices'] ?? []) }} {{ Str::plural('device', count($nestedChain['devices'] ?? [])) }}
                                                                                        </span>
                                                                                    </div>
                                                                                </div>
                                                                                
                                                                                <!-- Nested Chain Devices -->
                                                                                <div 
                                                                                    x-show="expandedNestedChain" 
                                                                                    x-collapse
                                                                                    class="ml-4 mt-2"
                                                                                >
                                                                                    @foreach($nestedChain['devices'] ?? [] as $nestedDeviceIndex => $nestedDevice)
                                                                                        <div class="relative">
                                                                                            <!-- Vertical Line (except for last item) -->
                                                                                            @if($nestedDeviceIndex < count($nestedChain['devices'] ?? []) - 1)
                                                                                                <div class="absolute left-4 top-8 bottom-0 w-px" style="background-color: #6C6C6C;"></div>
                                                                                            @endif
                                                                                            
                                                                                            <!-- Horizontal Line -->
                                                                                            <div class="absolute left-4 top-4 w-6 h-px" style="background-color: #6C6C6C;"></div>
                                                                                            
                                                                                            <!-- Nested Device Node -->
                                                                                            <div class="relative pl-10 pb-2">
                                                                                                <div class="flex items-center gap-3 p-2 rounded-lg transition-all hover:bg-opacity-10 hover:bg-white" style="background-color: rgba(13, 13, 13, 0.3);">
                                                                                                    <!-- Nested Device Icon -->
                                                                                                    <div class="flex items-center justify-center w-5 h-5 rounded-full" style="background-color: #01DA48;">
                                                                                                        <div class="w-2 h-2 rounded-full" style="background-color: #0D0D0D;"></div>
                                                                                                    </div>
                                                                                                    
                                                                                                    <!-- Nested Device Info -->
                                                                                                    <div class="flex-1 flex items-center gap-2">
                                                                                                        <span class="text-xs" style="color: #BBBBBB;">
                                                                                                            {{ $nestedDevice['name'] ?? 'Unknown Device' }}
                                                                                                        </span>
                                                                                                        @if(isset($nestedDevice['preset']) && $nestedDevice['preset'])
                                                                                                            <span class="text-xs px-2 py-0.5 rounded-full" style="background-color: #F87680; color: #0D0D0D;">
                                                                                                                {{ $nestedDevice['preset'] }}
                                                                                                            </span>
                                                                                                        @endif
                                                                                                        @if(isset($nestedDevice['type']))
                                                                                                            <span class="text-xs" style="color: #6C6C6C;">
                                                                                                                {{ $nestedDevice['type'] }}
                                                                                                            </span>
                                                                                                        @endif
                                                                                                    </div>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                    @endforeach
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                        
                                        <!-- Remove line for last chain -->
                                        @if($chainIndex === count($rackData['chains']) - 1)
                                            <div class="absolute left-4 top-12 bottom-0 w-px" style="background-color: #4a4a4a;"></div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Empty State -->
                @if(empty($rackData['chains']))
                    <div class="text-center py-12" style="color: #6C6C6C;">
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
            <div class="rounded-lg p-4" style="background-color: #4a4a4a; border: 1px solid #6C6C6C;">
                <h3 class="font-bold mb-3" style="color: #BBBBBB;">Technical Details</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span style="color: #6C6C6C;">Ableton Version:</span>
                        <span style="color: #BBBBBB;">{{ $rack->ableton_version ?: 'Unknown' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: #6C6C6C;">File Size:</span>
                        <span style="color: #BBBBBB;">{{ number_format($rack->file_size / 1024, 1) }} KB</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: #6C6C6C;">Published:</span>
                        <span style="color: #BBBBBB;">
                            {{ $rack->published_at ? $rack->published_at->format('M d, Y') : 'Pending' }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: #6C6C6C;">Status:</span>
                        <span class="text-xs px-2 py-1 rounded" style="background-color: #01DA48; color: #0D0D0D;">
                            {{ ucfirst($rack->status) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="rounded-lg p-4" style="background-color: #4a4a4a; border: 1px solid #6C6C6C;">
                <h3 class="font-bold mb-3" style="color: #BBBBBB;">Activity</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span style="color: #6C6C6C;">Last viewed:</span>
                        <span style="color: #BBBBBB;">{{ $rack->updated_at->diffForHumans() }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: #6C6C6C;">Comments:</span>
                        <span style="color: #BBBBBB;">{{ $rack->comments_count }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: #6C6C6C;">Likes:</span>
                        <span style="color: #BBBBBB;">{{ $rack->likes_count }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Flash Messages -->
    @if(session()->has('success'))
        <div class="rounded-lg p-4 mb-4" style="background-color: #01DA48; color: #0D0D0D;">
            {{ session('success') }}
        </div>
    @endif
    
    @if(session()->has('error'))
        <div class="rounded-lg p-4 mb-4" style="background-color: #F87680; color: #0D0D0D;">
            {{ session('error') }}
        </div>
    @endif
</div>