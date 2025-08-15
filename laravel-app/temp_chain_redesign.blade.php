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