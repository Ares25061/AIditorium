<?php

namespace App\Http\Requests;

use App\Enums\TypesEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFileRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string',Rule::enum(TypesEnum::class)],
            'course_id' => 'sometimes|nullable|integer|exists:courses,id',
            'task_id' => 'sometimes|nullable|integer|exists:tasks,id',
            'is_public' => 'sometimes|bool',
        ];
    }
}
