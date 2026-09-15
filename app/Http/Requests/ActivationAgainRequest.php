<?php

namespace App\Http\Requests;

use App\Custom\CustomResponse;
use App\Models\Client;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ActivationAgainRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:' . env('MAX_EMAIL'),'exists:clients'],
            'token_fcm'=>'string|nullable'
        ];
    }
    public function messages(): array
     {
        $language = $this->query('lang');
        return [
            'alpha' => CustomResponse::responseValidation('lang', $language),
            'max' => CustomResponse::responseValidation('max', $language),
            'required' => CustomResponse::responseValidation('required', $language),
            'email' => CustomResponse::responseValidation('email', $language),
            'size' => CustomResponse::responseValidation('size', $language),
            'exists' => CustomResponse::responseValidation('exists', $language),
        ];
    }
    public function passedValidation()
    {
        $language = $this->query('lang');
        $client = Client::select('status')->firstWhere('email', $this->email);
        $validator = Validator([], []);
        
        if ($client && $client->status == 1) {
            $validator->errors()->add('email', CustomResponse::responseValidation('accountActivated', $language));
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
