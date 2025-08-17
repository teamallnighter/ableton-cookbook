<x-app-layout>
    {{-- Pass SEO data to layout --}}
    @php
        $seoMetaTags = app('App\Services\SeoService')->getUploadMetaTags();
    @endphp
    
    <x-slot name="breadcrumbs">
        [
            ['name' => 'Home', 'url' => route('home')],
            ['name' => 'Dashboard', 'url' => route('dashboard')],
            ['name' => 'Upload Rack', 'url' => route('racks.upload')]
        ]
    </x-slot>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Breadcrumb -->
        <div class="mb-8">
            <nav class="flex" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-3">
                    <li class="inline-flex items-center">
                        <a href="{{ route('dashboard') }}" class="link">
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <span class="text-gray-600">/</span>
                            <span class="ml-3 text-sm font-medium text-black">Upload Rack</span>
                        </div>
                    </li>
                </ol>
            </nav>
        </div>

        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-black mb-2">Upload Your Ableton Rack</h1>
            <p class="text-gray-700">Share your Ableton Live instrument racks, audio effect racks, and MIDI racks with music producers worldwide. Help grow the community and showcase your creativity.</p>
            
            {{-- Hidden SEO content --}}
            <div class="sr-only">
                <h2>Share Your Music Production Creations</h2>
                <p>Upload Ableton Live racks to share with fellow music producers. Whether you've created innovative instrument racks, powerful audio effect chains, or useful MIDI racks, share them with the Ableton Cookbook community.</p>
            </div>
        </div>

        <!-- Upload Form -->
        <div class="card card-body" x-data="uploadForm()">
            <form method="POST" action="{{ route('racks.store') }}" enctype="multipart/form-data">
                @csrf
                
                <!-- File Upload Section -->
                <div class="mb-8">
                    <label for="rack_file" class="block text-sm font-medium mb-3 text-black">
                        Ableton Rack File (.adg) *
                    </label>
                    
                    <div 
                        class="border-2 border-dashed border-gray-400 rounded-lg p-8 text-center transition-all cursor-pointer hover:border-vibrant-blue hover:bg-gray-50"
                        :class="isDragOver ? 'border-vibrant-blue bg-gray-50' : ''"
                        @dragover.prevent="isDragOver = true"
                        @dragleave.prevent="isDragOver = false"
                        @drop.prevent="handleDrop($event)"
                        @click="$refs.fileInput.click()"
                    >
                        <div x-show="!selectedFile">
                            <div class="w-16 h-16 mx-auto mb-4 bg-vibrant-blue rounded-full flex items-center justify-center border-2 border-black">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                            </div>
                            <p class="mb-2 text-black">
                                <span class="font-bold">Click to upload</span> or drag and drop
                            </p>
                            <p class="text-sm text-gray-600">
                                Ableton rack files (.adg) up to 10MB
                            </p>
                        </div>
                        
                        <div x-show="selectedFile" class="flex items-center justify-center">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-vibrant-green rounded-lg flex items-center justify-center border-2 border-black">
                                    <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p x-text="selectedFile?.name" class="text-black font-medium"></p>
                                    <p class="text-sm text-gray-600" x-text="formatFileSize(selectedFile?.size)"></p>
                                </div>
                                <button type="button" @click.stop="removeFile()" class="ml-4 text-vibrant-red hover:text-red-700 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <input 
                        type="file" 
                        id="rack_file"
                        name="rack_file" 
                        accept=".adg"
                        class="hidden"
                        x-ref="fileInput"
                        @change="handleFileSelect($event)"
                        aria-describedby="rack_file_help"
                    >
                    <div id="rack_file_help" class="sr-only">Upload your Ableton Live rack file (.adg format) to share with the community. File size limit is 10MB.</div>
                    
                    @error('rack_file')
                        <p class="mt-2 text-sm text-vibrant-red font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Title Field -->
                <div class="mb-6">
                    <label for="title" class="block text-sm font-medium mb-2 text-black">
                        Title *
                    </label>
                    <input 
                        type="text" 
                        id="title"
                        name="title" 
                        value="{{ old('title') }}"
                        required
                        maxlength="255"
                        class="input-field"
                        placeholder="e.g., Vintage Bass Rack, Ambient Pad Collection, Hip Hop Drums"
                        aria-describedby="title_help"
                    >
                    <div id="title_help" class="sr-only">Enter a descriptive title that helps other producers understand what your rack does. Include the type of sounds or effects it creates.</div>
                    @error('title')
                        <p class="mt-2 text-sm text-vibrant-red font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Description Field -->
                <div class="mb-6">
                    <label for="description" class="block text-sm font-medium mb-2 text-black">
                        Description *
                    </label>
                    <textarea 
                        id="description"
                        name="description" 
                        required
                        maxlength="1000"
                        rows="4"
                        class="input-field resize-none"
                        placeholder="Describe what your rack does, how to use it, and what makes it special. Include musical genres, sound characteristics, and any unique features..."
                        aria-describedby="description_help"
                    >{{ old('description') }}</textarea>
                    <div id="description_help" class="sr-only">Provide a detailed description of your rack including what sounds it makes, what musical styles it's good for, and any special features or techniques used.</div>
                    @error('description')
                        <p class="mt-2 text-sm text-vibrant-red font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Category Field -->
                <div class="mb-6">
                    <label for="category" class="block text-sm font-medium mb-2 text-black">
                        Category
                    </label>
                    <select name="category" id="category" class="input-field">
                        <option value="">Select a category</option>
                        <option value="Bass" {{ old('category') === 'Bass' ? 'selected' : '' }}>Bass</option>
                        <option value="Lead" {{ old('category') === 'Lead' ? 'selected' : '' }}>Lead</option>
                        <option value="Pad" {{ old('category') === 'Pad' ? 'selected' : '' }}>Pad</option>
                        <option value="Drum" {{ old('category') === 'Drum' ? 'selected' : '' }}>Drum</option>
                        <option value="FX" {{ old('category') === 'FX' ? 'selected' : '' }}>FX</option>
                        <option value="Utility" {{ old('category') === 'Utility' ? 'selected' : '' }}>Utility</option>
                        <option value="Other" {{ old('category') === 'Other' ? 'selected' : '' }}>Other</option>
                    </select>
                    @error('category')
                        <p class="mt-2 text-sm text-vibrant-red font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Ableton Edition Field -->
                <div class="mb-6">
                    <label for="ableton_edition" class="block text-sm font-medium mb-2 text-black">
                        Minimum Ableton Live Edition Required
                    </label>
                    <select name="ableton_edition" id="ableton_edition" class="input-field">
                        <option value="">Auto-detect from file</option>
                        <option value="intro" {{ old('ableton_edition') === 'intro' ? 'selected' : '' }}>Live Intro</option>
                        <option value="standard" {{ old('ableton_edition') === 'standard' ? 'selected' : '' }}>Live Standard</option>
                        <option value="suite" {{ old('ableton_edition') === 'suite' ? 'selected' : '' }}>Live Suite</option>
                    </select>
                    <p class="mt-1 text-sm text-gray-600">
                        This helps users know if they can use your rack with their version of Live
                    </p>
                    @error('ableton_edition')
                        <p class="mt-2 text-sm text-vibrant-red font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Tags Field -->
                <div class="mb-8">
                    <label for="tags" class="block text-sm font-medium mb-2 text-black">
                        Tags (optional)
                    </label>
                    <input 
                        type="text" 
                        id="tags"
                        name="tags" 
                        value="{{ old('tags') }}"
                        maxlength="500"
                        class="input-field"
                        placeholder="e.g., vintage, warm, reverb, lead, electronic, ambient (comma separated)"
                        aria-describedby="tags_help"
                    >
                    <p id="tags_help" class="mt-1 text-sm text-gray-600">
                        Add specific tags to help others find your rack. Include genres, sound characteristics, instruments, and effects. Separate multiple tags with commas.
                    </p>
                    @error('tags')
                        <p class="mt-2 text-sm text-vibrant-red font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Submit Section -->
                <div class="border-t-2 border-black pt-6">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-600">
                            <p>Your rack will be automatically analyzed and published once processing is complete.</p>
                        </div>
                        
                        <div class="flex gap-4">
                            <a href="{{ route('dashboard') }}" class="btn-secondary">
                                Cancel
                            </a>
                            
                            <button type="submit" class="btn-primary">
                                Upload Rack
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        function uploadForm() {
            return {
                selectedFile: null,
                isDragOver: false,
                
                handleFileSelect(event) {
                    const file = event.target.files[0];
                    if (file && file.name.endsWith('.adg')) {
                        this.selectedFile = file;
                    } else {
                        alert('Please select a valid .adg file');
                        event.target.value = '';
                    }
                },
                
                handleDrop(event) {
                    this.isDragOver = false;
                    const files = event.dataTransfer.files;
                    if (files.length > 0) {
                        const file = files[0];
                        if (file.name.endsWith('.adg')) {
                            this.selectedFile = file;
                            this.$refs.fileInput.files = files;
                        } else {
                            alert('Please select a valid .adg file');
                        }
                    }
                },
                
                removeFile() {
                    this.selectedFile = null;
                    this.$refs.fileInput.value = '';
                },
                
                formatFileSize(bytes) {
                    if (!bytes) return '';
                    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                    const i = Math.floor(Math.log(bytes) / Math.log(1024));
                    return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
                }
            }
        }
    </script>
    @endpush
</x-app-layout>