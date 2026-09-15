<?php

namespace App\Http\Requests;

use App\Custom\CustomResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class DoctorAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        $language = $this->query('lang');

        return [
            'required' => CustomResponse::responseValidation('required', $language),
            'date_format' => CustomResponse::responseValidation('date', $language),
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        CustomResponse::failValidation($validator);
    }
}
