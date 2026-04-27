<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTaskRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'course_id' => 'required|exists:courses,id',
            'discipline_id' => 'required|exists:disciplines,id',
            'name' => 'required|string',
            'description' => 'sometimes|string',
            'scores' => 'sometimes|integer',
            'deadline' => 'sometimes|date',
            'attachment' => 'sometimes|file|max:102400',
            'attachments' => 'sometimes|array',
            'attachments.*' => 'file|max:102400',
        ];
    }
}
