<?php

namespace App\Http\Requests;

use App\Custom\CustomResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class SocialRequest extends FormRequest
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
            'access_token' => ['required','string','max:'.env('MAX_ACCESS_TOKEN')],
            'driver' => ['required','regex:/^[a-zA-Z-]+$/u','max:'. env('MAX_DRIVER')],
            'token_fcm' => ['string','max:'.env('MAX_TOKEN_FCM')]
        ];
    }
    public function messages()
    {
        $language = $this->query('lang');
        return [
            'lang.alpha' => CustomResponse::responseValidation('alpha', $language),
            'lang.size' => CustomResponse::responseValidation('size', $language),
            'access_token.required' => CustomResponse::responseValidation('required', $language),
            'access_token.string' => CustomResponse::responseValidation('string', $language),
            'access_token.max' => CustomResponse::responseValidation('max', $language),
            'driver.required' => CustomResponse::responseValidation('required', $language),
            'driver.regex' => CustomResponse::responseValidation('regex', $language),
            'driver.max' => CustomResponse::responseValidation('max', $language),
            'token_fcm.max' => CustomResponse::responseValidation('max', $language),
            'token_fcm.string' => CustomResponse::responseValidation('string', $language),
        ];
    }
    public function passedValidation()
    {
        $drivers=['google-web','google','facebook','apple'];
        $language = $this->query('lang');
        $validator = Validator([], []);
        if (!in_array(Str::lower($this->driver), $drivers)) {
            $validator->errors()->add('driver', CustomResponse::responseValidation('notCorrectDriver', $language));
        }
        if ($validator->errors()->isNotEmpty()) {
            $this->failedValidation($validator);
        }
        $this->replace([
            'driver' => Str::lower($this->driver),
            'access_token' => $this->access_token,
            'token_fcm' => $this->token_fcm ?? null
        ]);
    }
    public function failedValidation(Validator $validator)
    {
        CustomResponse::failValidationFirst($validator);
    }
}
