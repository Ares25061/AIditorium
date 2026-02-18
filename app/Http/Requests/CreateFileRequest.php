<?php

namespace App\Http\Requests;

use App\TypesEnum;
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
            'file' => 'required|file',
            'type' => ['sometimes', 'string',Rule::enum(TypesEnum::class)],
            'course_id' => 'sometimes|int',
            'task_id' => 'sometimes|int',
            'is_public' => 'sometimes|bool',
        ];
    }
}
