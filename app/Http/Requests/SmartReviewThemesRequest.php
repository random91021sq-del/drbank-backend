<?php

namespace App\Http\Requests;

use App\Custom\CustomResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SmartReviewThemesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_exam_type' => ['required', 'string', 'max:50'],
            'id_specialty' => ['nullable', 'integer'],
            'id_area' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        $language = $this->query('lang');

        return [
            'required' => CustomResponse::responseValidation('required', $language),
            'string' => CustomResponse::responseValidation('string', $language),
            'max' => CustomResponse::responseValidation('max', $language),
            'integer' => CustomResponse::responseValidation('integer', $language),
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        CustomResponse::failValidation($validator);
    }
}
