<?php

namespace App\Http\Requests;

use App\Custom\CustomResponse;
use Illuminate\Foundation\Http\FormRequest;
use App\Models\Client;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Str;

class ActivationRequest extends FormRequest
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
            'code' => ['required', 'string', 'alpha_num', 'size:' . env('DIGITS_CODE')]
        ];
    }
    public function messages():array
     {
        $language = $this->query('lang');
        return [
            'alpha' => CustomResponse::responseValidation('lang', $language),
            'required' => CustomResponse::responseValidation('required', $language),
            'string' => CustomResponse::responseValidation('string', $language),
            'email' => CustomResponse::responseValidation('email', $language),
            'exists' => CustomResponse::responseValidation('exists', $language),
            'alpha_num' => CustomResponse::responseValidation('alpha_num', $language),
            'size' => CustomResponse::responseValidation('size', $language),
            'max' => CustomResponse::responseValidation('max', $language),
        ];
    }

    public function passedValidation()
     {
        $language = $this->query('lang');
        $user = Client::select(['code_active','status'])->firstWhere('email', $this->email);
        $validator = Validator([], []);
        
        if ($user && $user->code_active != Str::lower($this->code)) {
            $validator->errors()->add('code', CustomResponse::responseValidation('invalidCode', $language));
        }
        if ($user && $user->status == 1) {
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
