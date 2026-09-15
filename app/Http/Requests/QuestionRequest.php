<?php

namespace App\Http\Requests;

use App\Custom\CustomResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class QuestionRequest extends FormRequest
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
            'specialty' => ['nullable','numeric'],
            'theme' => ['nullable','string'],
            'year' => ['nullable','array'],
            'year.*' => ['string'],
            'exam' => 'required|string',
            'count' => 'required|numeric'
        ];
    }
    public function messages()
    {
        $language = $this->query('lang');
        return [
            'alpha' => CustomResponse::responseValidation('alpha', $language),
            'size' => CustomResponse::responseValidation('size', $language),
            'required' => CustomResponse::responseValidation('required', $language),
            'string' => CustomResponse::responseValidation('string', $language),
            'regex' => CustomResponse::responseValidation('categoryId', $language),
            'numeric' => CustomResponse::responseValidation('numeric', $language)
        ];
    }
    public function failedValidation(Validator $validator)
    {
        CustomResponse::failValidation($validator);
    }
}
