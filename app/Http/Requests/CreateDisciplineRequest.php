<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateDisciplineRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'course_id' => 'required|integer|exists:courses,id',
            'name' => 'required|string',
            'description' => 'sometimes|string',
            'hours' => 'sometimes|integer',
            'discipline_slug' => 'sometimes|string',
        ];
    }
}
