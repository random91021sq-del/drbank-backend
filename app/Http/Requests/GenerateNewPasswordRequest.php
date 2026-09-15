<?php

namespace App\Http\Requests;

use App\Custom\CustomResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class GenerateNewPasswordRequest extends FormRequest
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
            'newPassword' => [
                'required',
                'string',
                Password::min(env('MIN_PASSWORD'))->max(env('MAX_PASSWORD'))
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
            ]
        ];
    }
     public function messages()
    {
        $language = $this->query('lang');
        return [
            'alpha' => CustomResponse::responseValidation('lang', $language),
            'size' => CustomResponse::responseValidation('size', $language),
            'required' => CustomResponse::responseValidation('required', $language),
            'string' => CustomResponse::responseValidation('string', $language),
            'newPassword.min' => CustomResponse::responseValidation('min', $language),
            'newPassword.max' => CustomResponse::responseValidation('max', $language),
            'newPassword.letters' => CustomResponse::responseValidation('letters', $language),
            'newPassword.mixed' => CustomResponse::responseValidation('mixedCase', $language),
            'newPassword.numbers' => CustomResponse::responseValidation('numbers', $language),
            'newPassword.symbols' => CustomResponse::responseValidation('symbols', $language),
        ];
    }
        public function failedValidation(Validator $validator)
    {
        CustomResponse::failValidation($validator);
    }
}
