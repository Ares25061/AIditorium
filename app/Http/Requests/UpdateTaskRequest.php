<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string',
            'description' => 'sometimes|string',
            'scores' => 'sometimes|integer',
            'deadline' => 'sometimes|date',
            'attachment' => 'sometimes|file|max:10240',
            'attachments' => 'sometimes|array',
            'attachments.*' => 'file|max:10240',
            'removed_attachment_ids' => 'sometimes|array',
            'removed_attachment_ids.*' => 'integer|exists:files,id',
        ];
    }

    public function messages(): array
    {
        return [
            'attachment.max' => __('messages.file_upload_too_large', ['max' => '10 MB']),
            'attachment.uploaded' => __('messages.file_upload_failed'),
            'attachments.*.max' => __('messages.file_upload_too_large', ['max' => '10 MB']),
            'attachments.*.uploaded' => __('messages.file_upload_failed'),
        ];
    }
}
