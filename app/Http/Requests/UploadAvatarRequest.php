<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadAvatarRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'avatar' => [
                'required',
                'image',
                'mimes:jpeg,png,jpg,webp',
                'max:3072',
                'dimensions:min_width=100,min_height=100,max_width=2000,max_height=2000',
            ],
            'user_id' => [
                'sometimes',
                'integer',
                'exists:users,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.image' => __('messages.avatar_upload_not_image'),
            'avatar.mimes' => __('messages.avatar_upload_invalid_type'),
            'avatar.max' => __('messages.avatar_upload_too_large'),
            'avatar.dimensions' => __('messages.avatar_upload_invalid_dimensions'),
        ];
    }
}
