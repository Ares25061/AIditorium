<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QueueAiReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'force_recheck' => 'sometimes|boolean',
        ];
    }
}
