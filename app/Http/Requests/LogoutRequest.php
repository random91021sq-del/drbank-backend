<?php

namespace App\Http\Requests;

use App\Custom\CustomResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Laravel\Sanctum\PersonalAccessToken;

class LogoutRequest extends FormRequest
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
           'token_fcm' => ['string', 'max:' . env('MAX_TOKEN_FCM')]
        ];
    }

    public function messages():array
    {
        $language=$this->query('lang');
        return [
            'alpha'=>CustomResponse::responseValidation('alpha',$language),
            'size'=>CustomResponse::responseValidation('size',$language),
            'max' => CustomResponse::responseValidation('max',$language),
            'string'=>CustomResponse::responseValidation('string',$language),
        ];
    }
    protected function passedValidation()
    {
        $language = $this->query('lang');
        $validator = Validator([], []);
        $token = PersonalAccessToken::findToken($this->bearerToken());
        if (!$token) {
            $validator->errors()->add('token', CustomResponse::responseValidation('invalidToken', $language));
        }
        if ($validator->errors()->isNotEmpty()) {
            $this->failedValidation($validator);
        }
        $this->merge(['tokenable_id' => $token->tokenable_id]);
    }
    protected function failedValidation(Validator $validator)
    {
        CustomResponse::failValidation($validator);
    }
}
