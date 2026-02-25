<?php

namespace App\Http\Requests;

use App\StatusCourseEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCourseRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'status' => ['sometimes', 'string',Rule::enum(StatusCourseEnum::class)],
            'background_logo' => 'sometimes|file|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'description' => 'sometimes|string',
        ];
    }
}
