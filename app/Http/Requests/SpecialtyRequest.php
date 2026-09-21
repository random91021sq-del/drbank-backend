<?php

namespace App\Http\Requests;

use App\Custom\CustomResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SpecialtyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'lang' => ['alpha', 'size:' . env('DIGITS_LANGUAGE')],
            'specialty' => 'required|integer|digits_between:' . env('DIGITS_SPECIALTY'),
            'exam' => ['required', 'string'],
            'area' => ['nullable', 'integer'],
            'year' => ['nullable', 'array'],
            'year.*' => ['string'],
        ];
    }
    protected function prepareForValidation()
    {
        $this->merge([
            'specialty' => $this->specialty
        ]);
    }
    public function messages()
    {
        $language = $this->query('lang');
        return [
            'alpha' => CustomResponse::responseValidation('lang', $language),
            'size' => CustomResponse::responseValidation('size', $language),
            'required' => CustomResponse::responseValidation('required', $language),
            'integer' => CustomResponse::responseValidation('integer', $language),
            'digits_between' => CustomResponse::responseValidation('digits_between', $language)
        ];
    }
    public function failedValidation(Validator $validator)
    {
        CustomResponse::failValidation($validator);
    }
}
