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
                                    <button type="submit" 
                                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors">
                                        Update Post
                                    </button>
                                    
                                    <button type="button"
                                            onclick="document.getElementById('published_at').value = ''; document.getElementById('blog-form').submit();"
                                            class="w-full bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-md transition-colors">
                                        Save as Draft
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <!-- TinyMCE WYSIWYG Editor -->
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        // Initialize TinyMCE with the same config as create form
        tinymce.init({
            selector: '#content',
            height: 500,
            menubar: false,
            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'image', 'charmap',
                'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                'insertdatetime', 'media', 'table', 'preview', 'help', 'wordcount'
            ],
            toolbar: 'undo redo | blocks | ' +
                'bold italic forecolor | alignleft aligncenter ' +
                'alignright alignjustify | bullist numlist outdent indent | ' +
                'removeformat | help',
            content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, San Francisco, Segoe UI, Roboto, Helvetica Neue, sans-serif; font-size: 14px; }',
            images_upload_handler: function (blobInfo, success, failure) {
                const formData = new FormData();
                formData.append('image', blobInfo.blob(), blobInfo.filename());
                
                fetch('{{ route("admin.blog.upload-image") }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        success(data.url);
                    } else {
                        failure('Image upload failed');
                    }
                })
                .catch(() => {
                    failure('Image upload failed');
                });
            }
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