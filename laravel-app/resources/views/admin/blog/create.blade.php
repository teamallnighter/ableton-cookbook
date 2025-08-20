<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Create Blog Post') }}
            </h2>
            <a href="{{ route('admin.blog.index') }}" 
               class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                Back to Posts
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <form action="{{ route('admin.blog.store') }}" method="POST" enctype="multipart/form-data" id="blog-form">
                @csrf
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Main Content -->
                    <div class="lg:col-span-2">
                        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                            <div class="p-6">
                                <!-- Title -->
                                <div class="mb-6">
                                    <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                                        Title <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" 
                                           name="title" 
                                           id="title" 
                                           value="{{ old('title') }}"
                                           class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('title') border-red-300 @enderror"
                                           required>
                                    @error('title')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Excerpt -->
                                <div class="mb-6">
                                    <label for="excerpt" class="block text-sm font-medium text-gray-700 mb-2">
                                        Excerpt <span class="text-red-500">*</span>
                                    </label>
                                    <textarea name="excerpt" 
                                              id="excerpt" 
                                              rows="3"
                                              class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('excerpt') border-red-300 @enderror"
                                              placeholder="Brief description of the post..."
                                              required>{{ old('excerpt') }}</textarea>
                                    @error('excerpt')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Content -->
                                <div class="mb-6">
                                    <label for="content" class="block text-sm font-medium text-gray-700 mb-2">
                                        Content <span class="text-red-500">*</span>
                                    </label>
                                    <textarea name="content" 
                                              id="content" 
                                              rows="20"
                                              class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('content') border-red-300 @enderror"
                                              required>{{ old('content') }}</textarea>
                                    @error('content')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- SEO Section -->
                        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mt-6">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">SEO Settings</h3>
                                
                                <div class="grid grid-cols-1 gap-6">
                                    <div>
                                        <label for="meta_title" class="block text-sm font-medium text-gray-700 mb-2">
                                            Meta Title
                                        </label>
                                        <input type="text" 
                                               name="meta_title" 
                                               id="meta_title"
                                               value="{{ old('meta_title') }}"
                                               maxlength="60"
                                               class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                               placeholder="Leave empty to use post title">
                                        <p class="mt-1 text-sm text-gray-500">Recommended: 50-60 characters</p>
                                    </div>
                                    
                                    <div>
                                        <label for="meta_description" class="block text-sm font-medium text-gray-700 mb-2">
                                            Meta Description
                                        </label>
                                        <textarea name="meta_description" 
                                                  id="meta_description"
                                                  rows="3"
                                                  maxlength="160"
                                                  class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                                  placeholder="Leave empty to use excerpt">{{ old('meta_description') }}</textarea>
                                        <p class="mt-1 text-sm text-gray-500">Recommended: 150-160 characters</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="lg:col-span-1">
                        <!-- Publish Options -->
                        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mb-6">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Publish Options</h3>
                                
                                <!-- Category -->
                                <div class="mb-4">
                                    <label for="blog_category_id" class="block text-sm font-medium text-gray-700 mb-2">
                                        Category <span class="text-red-500">*</span>
                                    </label>
                                    <select name="blog_category_id" 
                                            id="blog_category_id" 
                                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('blog_category_id') border-red-300 @enderror"
                                            required>
                                        <option value="">Select Category</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}" 
                                                    {{ old('blog_category_id') == $category->id ? 'selected' : '' }}>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('blog_category_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Publish Date -->
                                <div class="mb-4">
                                    <label for="published_at" class="block text-sm font-medium text-gray-700 mb-2">
                                        Publish Date
                                    </label>
                                    <input type="datetime-local" 
                                           name="published_at" 
                                           id="published_at"
                                           value="{{ old('published_at') }}"
                                           class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <p class="mt-1 text-sm text-gray-500">Leave empty to save as draft</p>
                                </div>

                                <!-- Options -->
                                <div class="space-y-3">
                                    <label class="flex items-center">
                                        <input type="checkbox" 
                                               name="featured" 
                                               value="1"
                                               {{ old('featured') ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700">Featured Post</span>
                                    </label>

                                    <label class="flex items-center">
                                        <input type="checkbox" 
                                               name="is_active" 
                                               value="1"
                                               {{ old('is_active', true) ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700">Active</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Featured Image -->
                        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mb-6">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Featured Image</h3>
                                
                                <!-- Drag & Drop Area -->
                                <div id="image-upload-area" 
                                     class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-gray-400 transition-colors cursor-pointer">
                                    <div id="upload-content">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <div class="mt-4">
                                            <p class="text-sm text-gray-600">
                                                <span class="font-medium text-blue-600 hover:text-blue-500">Click to upload</span>
                                                or drag and drop
                                            </p>
                                            <p class="text-xs text-gray-500">PNG, JPG, GIF up to 5MB</p>
                                        </div>
                                    </div>
                                    <div id="image-preview" class="hidden">
                                        <img id="preview-image" class="max-w-full h-auto rounded" />
                                        <button type="button" 
                                                id="remove-image"
                                                class="mt-2 text-sm text-red-600 hover:text-red-800">
                                            Remove Image
                                        </button>
                                    </div>
                                </div>
                                
                                <input type="file" 
                                       name="featured_image" 
                                       id="featured_image"
                                       accept="image/*"
                                       class="hidden">
                                       
                                @error('featured_image')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                            <div class="p-6">
                                <div class="flex flex-col space-y-3">
                                    <!-- Publish Now Button -->
                                    <button type="submit" 
                                            name="action" 
                                            value="publish_now"
                                            class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-md transition-colors">
                                        🚀 Publish Now
                                    </button>
                                    
                                    <!-- Create as Draft Button -->
                                    <button type="submit" 
                                            name="action" 
                                            value="save_draft"
                                            class="w-full bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-md transition-colors">
                                        📝 Save as Draft
                                    </button>
                                    
                                    <!-- Schedule for Later (uses custom publish date) -->
                                    <button type="submit" 
                                            name="action" 
                                            value="update"
                                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors">
                                        📅 Create Post
                                    </button>
                                </div>
                                
                                <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-md">
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 text-blue-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                        </svg>
                                        <span class="text-sm text-blue-800 font-medium">Tip</span>
                                    </div>
                                    <p class="text-xs text-blue-600 mt-1">
                                        Use "Publish Now" for immediate publishing, "Save as Draft" to work on it later, or set a publish date above and click "Create Post" to schedule.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <!-- Simple Rich Text Editor using execCommand -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const contentTextarea = document.getElementById('content');
            
            // Create editor container
            const editorContainer = document.createElement('div');
            editorContainer.className = 'border border-gray-300 rounded-md';
            
            // Create toolbar
            const toolbar = document.createElement('div');
            toolbar.className = 'border-b border-gray-300 p-2 bg-gray-50 flex flex-wrap gap-1';
            toolbar.innerHTML = `
                <button type="button" onclick="formatText('bold')" class="px-2 py-1 border rounded hover:bg-gray-200" title="Bold">
                    <strong>B</strong>
                </button>
                <button type="button" onclick="formatText('italic')" class="px-2 py-1 border rounded hover:bg-gray-200" title="Italic">
                    <em>I</em>
                </button>
                <button type="button" onclick="formatText('underline')" class="px-2 py-1 border rounded hover:bg-gray-200" title="Underline">
                    <u>U</u>
                </button>
                <div class="border-l mx-1"></div>
                <button type="button" onclick="formatText('insertUnorderedList')" class="px-2 py-1 border rounded hover:bg-gray-200" title="Bullet List">
                    • List
                </button>
                <button type="button" onclick="formatText('insertOrderedList')" class="px-2 py-1 border rounded hover:bg-gray-200" title="Numbered List">
                    1. List
                </button>
                <div class="border-l mx-1"></div>
                <button type="button" onclick="insertLink()" class="px-2 py-1 border rounded hover:bg-gray-200" title="Insert Link">
                    🔗 Link
                </button>
                <button type="button" onclick="formatText('removeFormat')" class="px-2 py-1 border rounded hover:bg-gray-200" title="Remove Formatting">
                    Clear
                </button>
                <div class="border-l mx-1"></div>
                <button type="button" onclick="toggleSource()" class="px-2 py-1 border rounded hover:bg-gray-200" title="View Source">
                    &lt;/&gt; HTML
                </button>
            `;
            
            // Create editable content area
            const editableContent = document.createElement('div');
            editableContent.contentEditable = true;
            editableContent.className = 'p-4 min-h-96 focus:outline-none';
            editableContent.style.minHeight = '400px';
            editableContent.innerHTML = contentTextarea.value || '<p>Start writing your blog post...</p>';
            
            // Replace textarea with editor
            editorContainer.appendChild(toolbar);
            editorContainer.appendChild(editableContent);
            contentTextarea.style.display = 'none';
            contentTextarea.parentNode.insertBefore(editorContainer, contentTextarea);
            
            // Sync content back to textarea
            editableContent.addEventListener('input', function() {
                contentTextarea.value = editableContent.innerHTML;
            });
            
            // Global functions for toolbar
            window.formatText = function(command, value = null) {
                editableContent.focus();
                document.execCommand(command, false, value);
                contentTextarea.value = editableContent.innerHTML;
            };
            
            window.insertLink = function() {
                const url = prompt('Enter URL:');
                if (url) {
                    formatText('createLink', url);
                }
            };
            
            let showingSource = false;
            window.toggleSource = function() {
                if (showingSource) {
                    // Switch back to visual editor
                    editableContent.innerHTML = contentTextarea.value;
                    editableContent.contentEditable = true;
                    editableContent.style.fontFamily = '';
                    showingSource = false;
                } else {
                    // Switch to HTML source
                    contentTextarea.value = editableContent.innerHTML;
                    editableContent.textContent = contentTextarea.value;
                    editableContent.contentEditable = true;
                    editableContent.style.fontFamily = 'monospace';
                    showingSource = true;
                }
            };
            
            // Update textarea before form submission
            const form = document.getElementById('blog-form');
            form.addEventListener('submit', function() {
                if (!showingSource) {
                    contentTextarea.value = editableContent.innerHTML;
                } else {
                    contentTextarea.value = editableContent.textContent;
                }
            });
        });

        // Drag and Drop Image Upload
        const uploadArea = document.getElementById('image-upload-area');
        const fileInput = document.getElementById('featured_image');
        const uploadContent = document.getElementById('upload-content');
        const imagePreview = document.getElementById('image-preview');
        const previewImage = document.getElementById('preview-image');
        const removeButton = document.getElementById('remove-image');

        uploadArea.addEventListener('click', () => {
            if (!imagePreview.classList.contains('hidden')) return;
            fileInput.click();
        });

        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('border-blue-400', 'bg-blue-50');
        });

        uploadArea.addEventListener('dragleave', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('border-blue-400', 'bg-blue-50');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('border-blue-400', 'bg-blue-50');
            
            const files = e.dataTransfer.files;
            if (files.length > 0 && files[0].type.startsWith('image/')) {
                fileInput.files = files;
                handleFileSelect(files[0]);
            }
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileSelect(e.target.files[0]);
            }
        });

        removeButton.addEventListener('click', () => {
            fileInput.value = '';
            uploadContent.classList.remove('hidden');
            imagePreview.classList.add('hidden');
        });

        function handleFileSelect(file) {
            if (!file.type.startsWith('image/')) {
                alert('Please select an image file.');
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                previewImage.src = e.target.result;
                uploadContent.classList.add('hidden');
                imagePreview.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        }
    </script>
    @endpush
</x-app-layout>