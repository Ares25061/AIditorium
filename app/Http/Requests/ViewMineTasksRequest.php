<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ViewMineTasksRequest extends FormRequest
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
            'discipline_id' => 'sometimes|integer|exists:disciplines,id',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ];
    }
}
