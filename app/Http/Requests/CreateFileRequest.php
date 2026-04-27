<?php

namespace App\Http\Requests;

use App\Enums\TypesEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateFileRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => 'required|file|max:10240',
            'type' => ['sometimes', 'string',Rule::enum(TypesEnum::class)],
            'course_id' => 'sometimes|int',
            'task_id' => 'sometimes|int',
            'is_public' => 'sometimes|bool',
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => __('messages.file_upload_too_large', ['max' => '10 MB']),
            'file.uploaded' => __('messages.file_upload_failed'),
        ];
    }
}
