<?php

namespace App\Http\Requests;

class CreateSmartReviewBlockRequest extends SmartReviewRequest
{
    public function rules(): array
    {
        return [
            'themes' => ['required', 'array', 'min:4', 'max:20'],
            'themes.*.id_theme' => ['required', 'uuid'],
            'themes.*.id_exam_type' => ['required', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return $this->commonMessages();
    }
}
