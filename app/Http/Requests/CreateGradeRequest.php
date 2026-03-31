<?php


namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:users,id',
            'course_id' => 'required|integer|exists:courses,id',
            'task_id' => 'sometimes|nullable|integer|exists:tasks,id',
            'discipline_id' => 'sometimes|nullable|integer|exists:disciplines,id',
            'file_id' => 'sometimes|nullable|integer|exists:files,id',
            'grade' => 'required|integer|min:0|max:100',
        ];
    }
}
