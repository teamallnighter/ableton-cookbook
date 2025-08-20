<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Edit Blog Post') }}
            </h2>
            <div class="flex space-x-3">
                @if($post->is_published)
                    <a href="{{ route('blog.show', $post->slug) }}" 
                       target="_blank"
                       class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        View Post
                    </a>
                @endif
                <a href="{{ route('admin.blog.index') }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    Back to Posts
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <form action="{{ route('admin.blog.update', $post) }}" method="POST" enctype="multipart/form-data" id="blog-form">
                @csrf
                @method('PUT')
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
                                           value="{{ old('title', $post->title) }}"
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
                                              required>{{ old('excerpt', $post->excerpt) }}</textarea>
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
                                              required>{{ old('content', $post->content) }}</textarea>
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
                                               value="{{ old('meta_title', $post->meta['title'] ?? '') }}"
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
                                                  placeholder="Leave empty to use excerpt">{{ old('meta_description', $post->meta['description'] ?? '') }}</textarea>
                                        <p class="mt-1 text-sm text-gray-500">Recommended: 150-160 characters</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Post Stats -->
                        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mt-6">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Post Statistics</h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div class="text-center">
                                        <div class="text-3xl font-bold text-blue-600">{{ number_format($post->views_count) }}</div>
                                        <div class="text-sm text-gray-500">Views</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-3xl font-bold text-green-600">{{ $post->reading_time }}</div>
                                        <div class="text-sm text-gray-500">Min Read</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-3xl font-bold text-purple-600">{{ $post->updated_at->diffForHumans() }}</div>
                                        <div class="text-sm text-gray-500">Last Updated</div>
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
                                                    {{ old('blog_category_id', $post->blog_category_id) == $category->id ? 'selected' : '' }}>
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
                                           value="{{ old('published_at', $post->published_at ? $post->published_at->format('Y-m-d\TH:i') : '') }}"
                                           class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <p class="mt-1 text-sm text-gray-500">Leave empty to save as draft</p>
                                </div>

                                <!-- Slug -->
                                <div class="mb-4">
                                    <label for="slug" class="block text-sm font-medium text-gray-700 mb-2">
                                        URL Slug
                                    </label>
                                    <input type="text" 
                                           name="slug" 
                                           id="slug"
                                           value="{{ $post->slug }}"
                                           class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-gray-100"
                                           readonly>
                                    <p class="mt-1 text-sm text-gray-500">URL: /blog/{{ $post->slug }}</p>
                                </div>

                                <!-- Options -->
                                <div class="space-y-3">
                                    <label class="flex items-center">
                                        <input type="checkbox" 
                                               name="featured" 
                                               value="1"
                                               {{ old('featured', $post->featured) ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700">Featured Post</span>
                                    </label>

                                    <label class="flex items-center">
                                        <input type="checkbox" 
                                               name="is_active" 
                                               value="1"
                                               {{ old('is_active', $post->is_active) ? 'checked' : '' }}
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
                                
                                <!-- Current Image -->
                                @if($post->featured_image_path)
                                    <div class="mb-4">
                                        <img src="{{ Storage::url($post->featured_image_path) }}" 
                                             alt="Current featured image" 
                                             class="w-full h-auto rounded-lg shadow-sm">
                                        <p class="mt-2 text-sm text-gray-500">Current image</p>
                                    </div>
                                @endif
                                
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
                                                {{ $post->featured_image_path ? ' a new image' : ' or drag and drop' }}
                                            </p>
                                            <p class="text-xs text-gray-500">PNG, JPG, GIF up to 5MB</p>
                                        </div>
                                    </div>
                                    <div id="image-preview" class="hidden">
                                        <img id="preview-image" class="max-w-full h-auto rounded" />
                                        <button type="button" 
                                                id="remove-image"
                                                class="mt-2 text-sm text-red-600 hover:text-red-800">
                                            Remove New Image
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
                                    @if(!$post->is_published)
                                        <!-- Publish Now Button (for drafts) -->
                                        <button type="submit" 
                                                name="action" 
                                                value="publish_now"
                                                class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-md transition-colors">
                                            🚀 Publish Now
                                        </button>
                                    @endif
                                    
                                    <!-- Update Post Button -->
                                    <button type="submit" 
                                            name="action" 
                                            value="update"
                                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors">
                                        {{ $post->is_published ? 'Update Published Post' : 'Update Post' }}
                                    </button>
                                    
                                    <!-- Save as Draft Button -->
                                    <button type="submit"
                                            name="action" 
                                            value="save_draft"
                                            class="w-full bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-md transition-colors">
                                        {{ $post->is_published ? 'Convert to Draft' : 'Save as Draft' }}
                                    </button>
                                </div>
                                
                                @if($post->is_published)
                                    <div class="mt-4 p-3 bg-green-50 border border-green-200 rounded-md">
                                        <div class="flex items-center">
                                            <svg class="w-4 h-4 text-green-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                            </svg>
                                            <span class="text-sm text-green-800 font-medium">Published</span>
                                        </div>
                                        <p class="text-xs text-green-600 mt-1">
                                            Published {{ $post->published_at->diffForHumans() }}
                                        </p>
                                    </div>
                                @else
                                    <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                                        <div class="flex items-center">
                                            <svg class="w-4 h-4 text-yellow-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                            </svg>
                                            <span class="text-sm text-yellow-800 font-medium">Draft</span>
                                        </div>
                                        <p class="text-xs text-yellow-600 mt-1">
                                            Not published yet
                                        </p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <!-- Markdown Editor with Preview -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const contentTextarea = document.getElementById('content');
            
            // Create editor container
            const editorContainer = document.createElement('div');
            editorContainer.className = 'border border-gray-300 rounded-md';
            
            // Create toolbar
            const toolbar = document.createElement('div');
            toolbar.className = 'border-b border-gray-300 p-2 bg-gray-50 flex flex-wrap gap-1 items-center';
            toolbar.innerHTML = `
                <button type="button" onclick="insertMarkdown('**', '**')" class="px-2 py-1 border rounded hover:bg-gray-200" title="Bold">
                    <strong>B</strong>
                </button>
                <button type="button" onclick="insertMarkdown('*', '*')" class="px-2 py-1 border rounded hover:bg-gray-200" title="Italic">
                    <em>I</em>
                </button>
                <button type="button" onclick="insertMarkdown('## ', '')" class="px-2 py-1 border rounded hover:bg-gray-200" title="Heading">
                    H2
                </button>
                <button type="button" onclick="insertMarkdown('### ', '')" class="px-2 py-1 border rounded hover:bg-gray-200" title="Subheading">
                    H3
                </button>
                <div class="border-l mx-1 h-6"></div>
                <button type="button" onclick="insertMarkdown('- ', '')" class="px-2 py-1 border rounded hover:bg-gray-200" title="Bullet List">
                    • List
                </button>
                <button type="button" onclick="insertMarkdown('1. ', '')" class="px-2 py-1 border rounded hover:bg-gray-200" title="Numbered List">
                    1. List
                </button>
                <button type="button" onclick="insertMarkdown('> ', '')" class="px-2 py-1 border rounded hover:bg-gray-200" title="Quote">
                    " Quote
                </button>
                <button type="button" onclick="insertMarkdown('\`', '\`')" class="px-2 py-1 border rounded hover:bg-gray-200" title="Inline Code">
                    &lt;/&gt;
                </button>
                <button type="button" onclick="insertCodeBlock()" class="px-2 py-1 border rounded hover:bg-gray-200" title="Code Block">
                    [Code]
                </button>
                <div class="border-l mx-1 h-6"></div>
                <button type="button" onclick="insertLink()" class="px-2 py-1 border rounded hover:bg-gray-200" title="Insert Link">
                    🔗 Link
                </button>
                <button type="button" onclick="insertImage()" class="px-2 py-1 border rounded hover:bg-gray-200" title="Insert Image">
                    🖼️ Image
                </button>
                <div class="border-l mx-1 h-6"></div>
                <button type="button" onclick="togglePreview()" class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600" title="Toggle Preview">
                    👁️ Preview
                </button>
                <span class="ml-auto text-sm text-gray-500">Markdown supported</span>
            `;
            
            // Create edit/preview container
            const contentContainer = document.createElement('div');
            contentContainer.className = 'relative';
            
            // Create new textarea for markdown
            const markdownTextarea = document.createElement('textarea');
            markdownTextarea.className = 'w-full p-4 font-mono text-sm border-0 resize-none focus:outline-none';
            markdownTextarea.style.minHeight = '400px';
            markdownTextarea.placeholder = 'Write your blog post in Markdown...\n\n**Bold** *Italic* [Link](url)\n\n## Heading 2\n### Heading 3\n\n- Bullet list\n1. Numbered list\n\n> Blockquote\n\n`inline code`\n\n```\ncode block\n```';
            markdownTextarea.value = contentTextarea.value || '';
            
            // Create preview div
            const previewDiv = document.createElement('div');
            previewDiv.className = 'w-full p-4 prose prose-lg max-w-none hidden';
            previewDiv.style.minHeight = '400px';
            
            // Add elements to containers
            contentContainer.appendChild(markdownTextarea);
            contentContainer.appendChild(previewDiv);
            editorContainer.appendChild(toolbar);
            editorContainer.appendChild(contentContainer);
            
            // Hide original textarea and insert new editor
            contentTextarea.style.display = 'none';
            contentTextarea.parentNode.insertBefore(editorContainer, contentTextarea);
            
            // Sync content back to original textarea
            markdownTextarea.addEventListener('input', function() {
                contentTextarea.value = markdownTextarea.value;
            });
            
            // Global functions for toolbar
            window.insertMarkdown = function(before, after) {
                const start = markdownTextarea.selectionStart;
                const end = markdownTextarea.selectionEnd;
                const selectedText = markdownTextarea.value.substring(start, end);
                const replacement = before + selectedText + after;
                
                markdownTextarea.value = markdownTextarea.value.substring(0, start) + replacement + markdownTextarea.value.substring(end);
                markdownTextarea.focus();
                markdownTextarea.setSelectionRange(start + before.length, start + before.length + selectedText.length);
                
                contentTextarea.value = markdownTextarea.value;
            };
            
            window.insertLink = function() {
                const url = prompt('Enter URL:');
                const text = prompt('Enter link text:') || 'link';
                if (url) {
                    const link = `[${text}](${url})`;
                    const start = markdownTextarea.selectionStart;
                    markdownTextarea.value = markdownTextarea.value.substring(0, start) + link + markdownTextarea.value.substring(markdownTextarea.selectionEnd);
                    markdownTextarea.focus();
                    contentTextarea.value = markdownTextarea.value;
                }
            };
            
            window.insertImage = function() {
                const url = prompt('Enter image URL:');
                const alt = prompt('Enter alt text:') || 'image';
                if (url) {
                    const img = `![${alt}](${url})`;
                    const start = markdownTextarea.selectionStart;
                    markdownTextarea.value = markdownTextarea.value.substring(0, start) + img + markdownTextarea.value.substring(markdownTextarea.selectionEnd);
                    markdownTextarea.focus();
                    contentTextarea.value = markdownTextarea.value;
                }
            };
            
            window.insertCodeBlock = function() {
                const lang = prompt('Enter language (optional):') || '';
                const code = '\n```' + lang + '\n\n```\n';
                const start = markdownTextarea.selectionStart;
                markdownTextarea.value = markdownTextarea.value.substring(0, start) + code + markdownTextarea.value.substring(markdownTextarea.selectionEnd);
                markdownTextarea.focus();
                markdownTextarea.setSelectionRange(start + 4 + lang.length, start + 4 + lang.length);
                contentTextarea.value = markdownTextarea.value;
            };
            
            let previewing = false;
            window.togglePreview = function() {
                if (previewing) {
                    markdownTextarea.classList.remove('hidden');
                    previewDiv.classList.add('hidden');
                    previewing = false;
                } else {
                    // Parse markdown to HTML
                    const htmlContent = marked.parse(markdownTextarea.value);
                    previewDiv.innerHTML = htmlContent;
                    markdownTextarea.classList.add('hidden');
                    previewDiv.classList.remove('hidden');
                    previewing = true;
                }
            };
            
            // Update form submission to ensure content is saved
            const form = document.getElementById('blog-form');
            form.addEventListener('submit', function() {
                contentTextarea.value = markdownTextarea.value;
            });
        });

        // Same drag and drop functionality as create form
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