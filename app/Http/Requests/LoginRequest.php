<?php

namespace App\Http\Requests;

use App\Custom\CustomResponse;
use App\Models\Client;
use App\Models\ClientFirebases;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class LoginRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:' . env('MAX_EMAIL'), 'exists:clients'],
            'password' => [
                'required',
                'string',
                Password::min(env('MIN_PASSWORD'))->max(env('MAX_PASSWORD'))
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
            ],
            'token_fcm' => ['string', 'max:' . env('MAX_TOKEN_FCM')]
        ];
    }

    protected function prepareForValidation()
    {
        $token_exist = '';
        if ($this->token_fcm) {
            $token_exist = ClientFirebases::where('token_firebase', $this->token_fcm)->exists() ? true : '';
        }

        $this->merge(['id' => $token_exist]);
    }

    protected function passedValidation()
    {
        $language = $this->query('lang');
        $validator = Validator([], []);
        $user = Client::select('status')->firstWhere('email', $this->email);

        if ($user && !Auth::attempt($this->only(['email', 'password']))) {
            $validator->errors()->add('password', CustomResponse::responseValidation('invalidPassword', $language));
        }

        if ($validator->errors()->isNotEmpty()) {
            $this->failedValidation($validator);
        }

        if ($user && $user->status != 1) {
            CustomResponse::responseNotActive('notActive', $language);
        }
    }
    
    public function messages()
    {
        $language = $this->query('lang');

        return [
            'alpha' => CustomResponse::responseValidation('lang', $language),
            'required' => CustomResponse::responseValidation('required', $language),
            'size' => CustomResponse::responseValidation('size', $language),
            'string' => CustomResponse::responseValidation('string', $language),
            'email' => CustomResponse::responseValidation('email', $language),
            'exists' => CustomResponse::responseValidation('exists', $language),
            'max' => CustomResponse::responseValidation('max', $language),
            'password.min' => CustomResponse::responseValidation('min', $language),
            'password.max' => CustomResponse::responseValidation('max', $language),
            'password.letters' => CustomResponse::responseValidation('letters', $language),
            'password.numbers' => CustomResponse::responseValidation('numbers', $language),
            'password.symbols' => CustomResponse::responseValidation('symbols', $language),
            'password.mixed' => CustomResponse::responseValidation('mixedCase', $language),
        ];
    }
    public function failedValidation(Validator $validator)
    {
        CustomResponse::failValidation($validator);
    }
}
