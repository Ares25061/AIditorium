<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_id' => 'required_without:parent_id|integer|exists:courses,id',
            'task_id' => 'sometimes|nullable|integer|exists:tasks,id',
            'discipline_id' => 'sometimes|nullable|integer|exists:disciplines,id',
            'file_id' => 'sometimes|nullable|integer|exists:files,id',
            'parent_id' => 'sometimes|nullable|integer|exists:comments,id',
            'body' => 'required|string|min:1|max:5000',
        ];
    }
}
