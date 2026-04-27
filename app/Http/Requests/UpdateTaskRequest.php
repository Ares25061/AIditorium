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
            'attachment' => 'sometimes|file|max:102400',
            'attachments' => 'sometimes|array',
            'attachments.*' => 'file|max:102400',
            'removed_attachment_ids' => 'sometimes|array',
            'removed_attachment_ids.*' => 'integer|exists:files,id',
        ];
    }
}
