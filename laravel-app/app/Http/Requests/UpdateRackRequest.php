<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'tags' => 'nullable|array|max:10',
            'tags.*' => 'string|max:50',
            'is_public' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'title.max' => 'The rack title cannot exceed 255 characters.',
            'description.max' => 'The rack description cannot exceed 1000 characters.',
            'tags.max' => 'You can add a maximum of 10 tags.',
            'tags.*.max' => 'Each tag cannot exceed 50 characters.',
        ];
    }
}