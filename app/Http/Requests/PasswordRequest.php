<?php

namespace App\Http\Requests;

use App\Custom\CustomResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordRequest extends FormRequest
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
            'password' => [
                'required',
                'string',
                Password::min(env('MIN_PASSWORD'))->max(env('MAX_PASSWORD'))
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
            ],
            'current_password' => [
                'required',
                'string',
                Password::min(env('MIN_PASSWORD'))->max(env('MAX_PASSWORD'))
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
            ],
        ];
    }
    public function messages():array
     {
        $language = $this->query('lang');
        return [
            'alpha' => CustomResponse::responseValidation('alpha', $language),
            'size' => CustomResponse::responseValidation('size', $language),
            'required' => CustomResponse::responseValidation('required', $language),
            'string' => CustomResponse::responseValidation('string', $language),
            'password.min' => CustomResponse::responseValidation('min', $language),
            'password.max' => CustomResponse::responseValidation('max', $language),
            'password.letters' => CustomResponse::responseValidation('letters', $language),
            'password.numbers' => CustomResponse::responseValidation('numbers', $language),
            'password.symbols' => CustomResponse::responseValidation('symbols', $language),
            'password.mixed' => CustomResponse::responseValidation('mixedCase', $language),
            'current_password.letters' => CustomResponse::responseValidation('letters', $language),
            'current_password.numbers' => CustomResponse::responseValidation('numbers', $language),
            'current_password.symbols' => CustomResponse::responseValidation('symbols', $language),
            'current_password.mixed' => CustomResponse::responseValidation('mixedCase', $language),
            'current_password.min' => CustomResponse::responseValidation('min', $language),
            'current_password.max' => CustomResponse::responseValidation('max', $language),
        ];
    }
    public function passedValidation()
    {
        $language = $this->query('lang');

        $userData = auth('sanctum')->user();
        $userDataPassword = (string)$userData->password;
        $validator = Validator([], []);
        if (!Hash::check($this->current_password, $userDataPassword)) {
            $validator->errors()->add('current_password', CustomResponse::responseValidation('invalidPassword', $language));
        } elseif (Hash::check($this->password, $userDataPassword)) {
            $validator->errors()->add('password', CustomResponse::responseValidation('notSamePassword', $language));
        }
        if ($validator->errors()->isNotEmpty()) {
            $this->failedValidation($validator);
        }
    }
    public function failedValidation(Validator $validator)
    {
        CustomResponse::failValidation($validator);
    }
}
