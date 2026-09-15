<?php

namespace App\Http\Requests;

use App\Custom\CustomResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:' . env('MIN_NAME'), 'max:' . env('MAX_NAME'), 'regex:/^[a-zA-Z\s]+$/'],
            'last_name' => ['required', 'string', 'min:' . env('MIN_LAST_NAME'), 'max:' . env('MAX_LAST_NAME'), 'regex:/^[a-zA-Z\s]+$/'],
            'email' => ['required', 'string', 'email', 'max:' . env('MAX_EMAIL'), 'unique:clients'],
            'password' => [
                'required',
                'string',
                Password::min(env('MIN_PASSWORD'))->max(env('MAX_PASSWORD'))
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
            ],
            'university'=>['nullable','string','alpha','min:'.env("MIN_UNIVERSITY"),'max:'.env("MAX_UNIVERSITY")],
            'token_fcm'=>'string|nullable',
        ];
    }

    public function messages()
    {
        $language = $this->query('lang');
        return [
            'lang.alpha' => CustomResponse::responseValidation('lang', $language),
            'alpha'=>CustomResponse::responseValidation('alpha',$language),
            'size' => CustomResponse::responseValidation('size', $language),
            'required' => CustomResponse::responseValidation('required', $language),
            'string' => CustomResponse::responseValidation('string', $language),
            'regex' => CustomResponse::responseValidation('regex', $language),
            'email' => CustomResponse::responseValidation('email', $language),
            'unique' => CustomResponse::responseValidation('userExist', $language),
            'min' => CustomResponse::responseValidation('min', $language),
            'max' => CustomResponse::responseValidation('max', $language),
            'password.min' => CustomResponse::responseValidation('min', $language),
            'password.max' => CustomResponse::responseValidation('max', $language),
            'password.mixed' => CustomResponse::responseValidation('mixedCase', $language),
            'password.letters' => CustomResponse::responseValidation('letters', $language),
            'password.numbers' => CustomResponse::responseValidation('numbers', $language),
            'password.symbols' => CustomResponse::responseValidation('symbols', $language),
        ];
    }

    public function failedValidation(Validator $validator)
    {
        CustomResponse::failValidation($validator);
    }
}
