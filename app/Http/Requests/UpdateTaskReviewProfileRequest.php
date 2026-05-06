<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskReviewProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enabled' => 'required|boolean',
            'rubric' => 'required|array|min:1',
            'rubric.*.id' => 'sometimes|string|max:100',
            'rubric.*.label' => 'required|string|max:255',
            'rubric.*.description' => 'required|string|max:5000',
            'rubric.*.instructions' => 'sometimes|nullable|string|max:5000',
            'rubric.*.weight' => 'sometimes|nullable|integer|min:0|max:10000',
            'rubric.*.checks' => 'sometimes|array',
            'rubric.*.checks.*' => 'required|string|max:1000',
            'custom_prompt' => 'sometimes|nullable|string|max:10000',
            'supported_formats' => 'sometimes|array',
            'supported_formats.*' => [
                'string',
                Rule::in(config('ai.supported_extensions', [])),
            ],
        ];
    }
}
