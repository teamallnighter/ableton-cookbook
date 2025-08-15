<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Upload Rack - {{ config('app.name', 'Ableton Cookbook') }}</title>

    <!-- Fonts -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/teamallnighter/abletonSans@latest/abletonSans.css">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased" style="background-color: #C3C3C3;">
    <!-- Navigation -->
    <nav class="shadow-sm border-b-2" style="background-color: #0D0D0D; border-color: #01CADA;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="{{ route('home') }}" class="flex items-center">
                        <span class="text-xl font-bold" style="color: #ffdf00;">🎵 Ableton Cookbook</span>
                    </a>
                </div>

                <!-- Auth Links -->
                <div class="flex items-center space-x-4">
                    @auth
                        <a href="{{ route('profile') }}" style="color: #BBBBBB;" class="hover:text-opacity-80 transition-colors">
                            My Profile
                        </a>
                        <a href="{{ route('dashboard') }}" style="color: #BBBBBB;" class="hover:text-opacity-80 transition-colors">
                            Dashboard
                        </a>
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" style="color: #BBBBBB;" class="hover:text-opacity-80 transition-colors">
                                Logout
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" style="color: #BBBBBB;" class="hover:text-opacity-80 transition-colors">
                            Login
                        </a>
                        <a href="{{ route('register') }}" style="background-color: #01CADA; color: #0D0D0D;" class="px-4 py-2 rounded-lg hover:opacity-90 transition-opacity font-semibold">
                            Register
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
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
                                Upload Rack
                            </span>
                        </div>
                    </li>
                </ol>
            </nav>
        </div>

        <!-- Upload Form -->
        <div class="rounded-lg p-8" style="background-color: #4a4a4a; border: 1px solid #6C6C6C;">
            <div class="mb-8">
                <h1 class="text-3xl font-bold mb-2" style="color: #BBBBBB;">Upload Your Rack</h1>
                <p style="color: #6C6C6C;">Share your Ableton Live rack with the community</p>
            </div>

            <form method="POST" action="{{ route('racks.store') }}" enctype="multipart/form-data" x-data="uploadForm()">
                @csrf
                
                <!-- File Upload -->
                <div class="mb-6">
                    <label class="block text-sm font-medium mb-3" style="color: #BBBBBB;">
                        Rack File (.adg)
                    </label>
                    
                    <div 
                        class="border-2 border-dashed rounded-lg p-8 text-center transition-colors cursor-pointer"
                        style="border-color: #6C6C6C; background-color: #0D0D0D;"
                        :style="isDragOver ? 'border-color: #01CADA; background-color: rgba(1, 218, 218, 0.1)' : ''"
                        @dragover.prevent="isDragOver = true"
                        @dragleave.prevent="isDragOver = false"
                        @drop.prevent="handleDrop($event)"
                        @click="$refs.fileInput.click()"
                    >
                        <div x-show="!selectedFile">
                            <svg class="w-12 h-12 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: #6C6C6C;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            <p class="mb-2" style="color: #BBBBBB;">
                                <span class="font-semibold">Click to upload</span> or drag and drop
                            </p>
                            <p class="text-sm" style="color: #6C6C6C;">
                                Ableton rack files (.adg) up to 10MB
                            </p>
                        </div>
                        
                        <div x-show="selectedFile" class="flex items-center justify-center">
                            <div class="flex items-center gap-3">
                                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24" style="color: #01CADA;">
                                    <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                                </svg>
                                <div>
                                    <p x-text="selectedFile?.name" style="color: #BBBBBB;"></p>
                                    <p class="text-sm" style="color: #6C6C6C;" x-text="formatFileSize(selectedFile?.size)"></p>
                                </div>
                                <button type="button" @click.stop="removeFile()" class="ml-4 hover:opacity-80 transition-opacity">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: #F87680;">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <input 
                        type="file" 
                        name="rack_file" 
                        accept=".adg"
                        class="hidden"
                        x-ref="fileInput"
                        @change="handleFileSelect($event)"
                    >
                    
                    @error('rack_file')
                        <p class="mt-2 text-sm" style="color: #F87680;">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Title -->
                <div class="mb-6">
                    <label for="title" class="block text-sm font-medium mb-2" style="color: #BBBBBB;">
                        Title *
                    </label>
                    <input 
                        type="text" 
                        id="title"
                        name="title" 
                        value="{{ old('title') }}"
                        required
                        maxlength="255"
                        class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:outline-none"
                        style="background-color: #0D0D0D; border-color: #6C6C6C; color: #BBBBBB;"
                        placeholder="Enter a descriptive title for your rack"
                    >
                    @error('title')
                        <p class="mt-2 text-sm" style="color: #F87680;">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Description -->
                <div class="mb-6">
                    <label for="description" class="block text-sm font-medium mb-2" style="color: #BBBBBB;">
                        Description *
                    </label>
                    <textarea 
                        id="description"
                        name="description" 
                        required
                        maxlength="1000"
                        rows="4"
                        class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:outline-none resize-none"
                        style="background-color: #0D0D0D; border-color: #6C6C6C; color: #BBBBBB;"
                        placeholder="Describe what your rack does, how to use it, and what makes it special..."
                    >{{ old('description') }}</textarea>
                    @error('description')
                        <p class="mt-2 text-sm" style="color: #F87680;">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Tags -->
                <div class="mb-8">
                    <label for="tags" class="block text-sm font-medium mb-2" style="color: #BBBBBB;">
                        Tags (optional)
                    </label>
                    <input 
                        type="text" 
                        id="tags"
                        name="tags" 
                        value="{{ old('tags') }}"
                        maxlength="500"
                        class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:outline-none"
                        style="background-color: #0D0D0D; border-color: #6C6C6C; color: #BBBBBB;"
                        placeholder="e.g., vintage, warm, reverb, lead (comma separated)"
                    >
                    <p class="mt-1 text-sm" style="color: #6C6C6C;">
                        Add specific tags to help others find your rack. Separate multiple tags with commas.
                    </p>
                    @error('tags')
                        <p class="mt-2 text-sm" style="color: #F87680;">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Submit Button -->
                <div class="flex items-center justify-between">
                    <div class="text-sm" style="color: #6C6C6C;">
                        <p>Your rack will be automatically analyzed and require approval before being published.</p>
                    </div>
                    
                    <div class="flex gap-4">
                        <a 
                            href="{{ route('home') }}"
                            class="px-6 py-3 rounded-lg border hover:opacity-80 transition-opacity"
                            style="border-color: #6C6C6C; color: #BBBBBB;"
                        >
                            Cancel
                        </a>
                        
                        <button 
                            type="submit"
                            class="px-6 py-3 rounded-lg font-semibold hover:opacity-90 transition-opacity"
                            style="background-color: #01CADA; color: #0D0D0D;"
                        >
                            Upload Rack
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </main>

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
</body>
</html>