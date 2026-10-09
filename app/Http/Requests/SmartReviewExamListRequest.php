<?php

namespace App\Http\Requests;

class SmartReviewExamListRequest extends SmartReviewRequest
{
    public function rules(): array
    {
        return [
            'page' => ['required', 'integer', 'min:1'],
            'limit' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return $this->commonMessages();
    }
}
