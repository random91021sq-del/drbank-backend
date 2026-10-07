<?php

namespace App\Http\Requests;

use App\Custom\CustomResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SpecialtyListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lang' => ['nullable', 'alpha', 'size:'.env('DIGITS_LANGUAGE')],
            'exam' => ['required', 'string'],
            'area' => ['required', 'array'],
            'area.*'=> ['integer'],
            'year' => ['nullable', 'array'],
            'year.*' => ['string'],
        ];
    }

    public function messages(): array
    {
        $language = $this->query('lang');

        return [
            'alpha' => CustomResponse::responseValidation('lang', $language),
            'size' => CustomResponse::responseValidation('size', $language),
            'required' => CustomResponse::responseValidation('required', $language),
            'string' => CustomResponse::responseValidation('string', $language),
            'integer' => CustomResponse::responseValidation('integer', $language),
            'array' => CustomResponse::responseValidation('array', $language),
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        CustomResponse::failValidation($validator);
    }
}
