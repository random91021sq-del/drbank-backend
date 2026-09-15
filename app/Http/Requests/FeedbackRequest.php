<?php

namespace App\Http\Requests;

use App\Custom\CustomResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class FeedbackRequest extends FormRequest
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
            'reason' => ['required'],
            'description' => ['required', 'string', 'min:' . env('MIN_DESCRIPTION'), 'max:' . env('MAX_DESCRIPTION'), 'regex:/^[\pL\s,.]+$/u'],
            'full_name' => ['required', 'string', 'min:' . env('MIN_FULL_NAME'), 'max:' . env('MAX_FULL_NAME'), 'regex:/^[\pL\s]+$/u'],
            'email' => ['required', 'string', 'email', 'max:' . env('MAX_EMAIL'), 'regex:/^[a-zA-Z0-9@._-]+$/u'],
            'response' => ['required', 'string', 'min:' . env('MIN_RESPONSE'), 'max:' . env('MAX_RESPONSE'), 'regex:/^[a-zA-Z0-9@._-]+$/u'],
        ];
    }
    public function messages()
    {
        $language = $this->query('lang');
        return [
            'required' => CustomResponse::responseValidation('required', $language),
            'alpha' => CustomResponse::responseValidation('alpha', $language),
            'size' => CustomResponse::responseValidation('size', $language),
            'max' => CustomResponse::responseValidation('max', $language),
            'min' => CustomResponse::responseValidation('min', $language),
            'string' => CustomResponse::responseValidation('string', $language),
            'regex' => CustomResponse::responseValidation('regex', $language),
            'email' => CustomResponse::responseValidation('email', $language),
        ];
    }
    public function failedValidation(Validator $validator)
    {
        CustomResponse::failValidation($validator);
    }
}
