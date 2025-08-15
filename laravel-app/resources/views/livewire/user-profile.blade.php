<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
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
                        <span class="ml-1 md:ml-2 text-sm font-medium" style="color: #0D0D0D;">
                            {{ $isOwnProfile ? 'My Profile' : $user->name . '\'s Profile' }}
                        </span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    <!-- Profile Header -->
    <div class="rounded-lg p-6 mb-8" style="background-color: #0D0D0D; border: 1px solid #4a4a4a;">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
            <!-- User Info -->
            <div class="flex items-center gap-4">
                <div class="w-20 h-20 rounded-full flex items-center justify-center text-3xl font-bold" style="background-color: #01CADA; color: #0D0D0D;">
                    {{ strtoupper(substr($user->name, 0, 2)) }}
                </div>
                <div>
                    <h1 class="text-3xl font-bold" style="color: #BBBBBB;">{{ $user->name }}</h1>
                    <p class="text-lg" style="color: #6C6C6C;">
                        @if($isOwnProfile)
                            Your Profile
                        @else
                            Community Member
                        @endif
                    </p>
                    <p class="text-sm" style="color: #6C6C6C;">
                        Member since {{ $user->created_at->format('F Y') }}
                    </p>
                </div>
            </div>

            <!-- Profile Stats -->
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-6 text-center">
                <div>
                    <div class="text-2xl font-bold" style="color: #01CADA;">{{ $stats['total_uploads'] }}</div>
                    <div class="text-sm" style="color: #6C6C6C;">Uploads</div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: #01CADA;">{{ number_format($stats['total_downloads']) }}</div>
                    <div class="text-sm" style="color: #6C6C6C;">Downloads</div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: #01CADA;">{{ number_format($stats['total_views']) }}</div>
                    <div class="text-sm" style="color: #6C6C6C;">Views</div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: #01CADA;">{{ $stats['total_favorites'] }}</div>
                    <div class="text-sm" style="color: #6C6C6C;">Favorited</div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: #ffdf00;">{{ number_format($stats['average_rating'], 1) }}</div>
                    <div class="text-sm" style="color: #6C6C6C;">Avg Rating</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="mb-8">
        <div class="rounded-lg p-1" style="background-color: #4a4a4a;">
            <div class="flex gap-1">
                <button 
                    wire:click="setActiveTab('uploads')"
                    class="flex-1 px-4 py-2 rounded text-sm font-medium transition-all"
                    style="background-color: {{ $activeTab === 'uploads' ? '#01CADA' : 'transparent' }}; color: {{ $activeTab === 'uploads' ? '#0D0D0D' : '#BBBBBB' }};"
                >
                    📤 Uploaded Racks ({{ $stats['total_uploads'] }})
                </button>
                @if($isOwnProfile)
                    <button 
                        wire:click="setActiveTab('favorites')"
                        class="flex-1 px-4 py-2 rounded text-sm font-medium transition-all"
                        style="background-color: {{ $activeTab === 'favorites' ? '#01CADA' : 'transparent' }}; color: {{ $activeTab === 'favorites' ? '#0D0D0D' : '#BBBBBB' }};"
                    >
                        💖 My Favorites
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Tab Content -->
    @if($activeTab === 'uploads')
        <!-- Uploaded Racks -->
        <div class="mb-8">
            <h2 class="text-xl font-bold mb-4" style="color: #0D0D0D;">
                {{ $isOwnProfile ? 'Your Uploaded Racks' : $user->name . '\'s Uploaded Racks' }}
            </h2>
            
            @if($racks->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8">
                    @foreach($racks as $rack)
                        <div class="rounded-lg shadow-sm border overflow-hidden hover:shadow-md transition-shadow cursor-pointer h-full flex flex-col" style="background-color: #4a4a4a; border-color: #6C6C6C;">
                            <!-- Rack Header -->
                            <div class="p-4 border-b" style="border-color: #6C6C6C;">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-semibold truncate">
                                            <a href="{{ route('racks.show', $rack) }}" style="color: #BBBBBB;" class="hover:opacity-80 transition-opacity">
                                                {{ $rack->title }}
                                            </a>
                                        </h3>
                                        <p class="text-sm mt-1" style="color: #BBBBBB;">
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
                                    
                                    <div class="flex items-center gap-2 ml-2">
                                        <!-- Favorite Heart -->
                                        @auth
                                            <button 
                                                wire:click="toggleFavorite({{ $rack->id }})"
                                                class="hover:scale-110 transition-transform"
                                            >
                                                <svg class="w-4 h-4" fill="{{ $rack->is_favorited_by_user ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24" style="color: #F87680;">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                                </svg>
                                            </button>
                                        @endauth
                                        
                                        <!-- Edition Badge -->
                                        @if($rack->ableton_edition)
                                            <span class="text-xs px-2 py-1 rounded font-medium" 
                                                  style="background-color: {{ $rack->ableton_edition === 'suite' ? '#01DA48' : ($rack->ableton_edition === 'standard' ? '#01CADA' : '#ffdf00') }}; color: #0D0D0D;">
                                                {{ ucfirst($rack->ableton_edition) }}
                                            </span>
                                        @endif
                                        
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
                                <p class="text-sm mb-3 line-clamp-2" style="color: #BBBBBB;">{{ $rack->description }}</p>
                                
                                <!-- Tags -->
                                <div class="flex flex-wrap gap-1 mb-3">
                                    @foreach($rack->tags->take(3) as $tag)
                                        <span class="inline-block text-xs px-2 py-1 rounded" style="background-color: #01CADA; color: #0D0D0D;">
                                            {{ $tag->name }}
                                        </span>
                                    @endforeach
                                    @if($rack->tags->count() > 3)
                                        <span class="text-xs" style="color: #6C6C6C;">+{{ $rack->tags->count() - 3 }} more</span>
                                    @endif
                                </div>

                                <!-- Actions -->
                                <div class="flex gap-2 mt-auto">
                                    <a 
                                        href="{{ route('racks.show', $rack) }}"
                                        class="flex-1 px-3 py-2 rounded hover:opacity-90 transition-opacity text-sm font-medium text-center"
                                        style="background-color: #01CADA; color: #0D0D0D;"
                                    >
                                        View Rack
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12">
                    <div class="text-6xl mb-4" style="color: #6C6C6C;">🎛️</div>
                    <h3 class="text-lg font-medium mb-2" style="color: #0D0D0D;">
                        {{ $isOwnProfile ? 'No uploaded racks yet' : 'No public racks found' }}
                    </h3>
                    <p style="color: #6C6C6C;">
                        {{ $isOwnProfile ? 'Upload your first rack to get started!' : 'This user hasn\'t uploaded any public racks yet.' }}
                    </p>
                </div>
            @endif
        </div>
    @elseif($activeTab === 'favorites' && $isOwnProfile)
        <!-- Favorite Racks -->
        <div class="mb-8">
            <h2 class="text-xl font-bold mb-4" style="color: #0D0D0D;">Your Favorite Racks</h2>
            
            @if($racks->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8">
                    @foreach($racks as $rack)
                        <div class="rounded-lg shadow-sm border overflow-hidden hover:shadow-md transition-shadow cursor-pointer h-full flex flex-col" style="background-color: #4a4a4a; border-color: #6C6C6C;">
                            <!-- Same rack card structure as above -->
                            <div class="p-4 border-b" style="border-color: #6C6C6C;">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-semibold truncate">
                                            <a href="{{ route('racks.show', $rack) }}" style="color: #BBBBBB;" class="hover:opacity-80 transition-opacity">
                                                {{ $rack->title }}
                                            </a>
                                        </h3>
                                        <p class="text-sm mt-1" style="color: #BBBBBB;">
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
                                    
                                    <div class="flex items-center gap-2 ml-2">
                                        <!-- Always show filled heart for favorites -->
                                        <button 
                                            wire:click="toggleFavorite({{ $rack->id }})"
                                            class="hover:scale-110 transition-transform"
                                        >
                                            <svg class="w-4 h-4" fill="currentColor" stroke="currentColor" viewBox="0 0 24 24" style="color: #F87680;">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                            </svg>
                                        </button>
                                        
                                        @if($rack->ableton_edition)
                                            <span class="text-xs px-2 py-1 rounded font-medium" 
                                                  style="background-color: {{ $rack->ableton_edition === 'suite' ? '#01DA48' : ($rack->ableton_edition === 'standard' ? '#01CADA' : '#ffdf00') }}; color: #0D0D0D;">
                                                {{ ucfirst($rack->ableton_edition) }}
                                            </span>
                                        @endif
                                        
                                        <div class="flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" style="color: #ffdf00;">
                                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                            </svg>
                                            <span class="text-sm" style="color: #BBBBBB;">{{ number_format($rack->average_rating, 1) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="p-4 flex-1 flex flex-col">
                                <p class="text-sm mb-3 line-clamp-2" style="color: #BBBBBB;">{{ $rack->description }}</p>
                                
                                <div class="flex flex-wrap gap-1 mb-3">
                                    @foreach($rack->tags->take(3) as $tag)
                                        <span class="inline-block text-xs px-2 py-1 rounded" style="background-color: #01CADA; color: #0D0D0D;">
                                            {{ $tag->name }}
                                        </span>
                                    @endforeach
                                    @if($rack->tags->count() > 3)
                                        <span class="text-xs" style="color: #6C6C6C;">+{{ $rack->tags->count() - 3 }} more</span>
                                    @endif
                                </div>

                                <div class="flex gap-2 mt-auto">
                                    <a 
                                        href="{{ route('racks.show', $rack) }}"
                                        class="flex-1 px-3 py-2 rounded hover:opacity-90 transition-opacity text-sm font-medium text-center"
                                        style="background-color: #01CADA; color: #0D0D0D;"
                                    >
                                        View Rack
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12">
                    <div class="text-6xl mb-4" style="color: #F87680;">💖</div>
                    <h3 class="text-lg font-medium mb-2" style="color: #0D0D0D;">No favorites yet</h3>
                    <p style="color: #6C6C6C;">Start exploring and heart racks you love!</p>
                    <a href="{{ route('home') }}" class="inline-block mt-4 px-4 py-2 rounded-lg hover:opacity-90 transition-opacity" style="background-color: #01CADA; color: #0D0D0D;">
                        Browse Racks
                    </a>
                </div>
            @endif
        </div>
    @endif

    <!-- Pagination -->
    @if($racks->hasPages())
        <div class="flex justify-center">
            {{ $racks->links() }}
        </div>
    @endif

    <!-- Flash Messages -->
    @if(session()->has('success'))
        <div class="fixed bottom-4 right-4 rounded-lg p-4 shadow-lg" style="background-color: #01DA48; color: #0D0D0D;">
            {{ session('success') }}
        </div>
    @endif
    
    @if(session()->has('error'))
        <div class="fixed bottom-4 right-4 rounded-lg p-4 shadow-lg" style="background-color: #F87680; color: #0D0D0D;">
            {{ session('error') }}
        </div>
    @endif
</div>