<?php

namespace App\Http\Requests;

use App\Custom\CustomResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class ExamUserRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'lang' => ['alpha', 'size:'.env('DIGITS_LANGUAGE')],
            'exam_type' => ['string', 'max:255'],
            'page' => ['required', 'integer'],
            'limit' => ['required', 'integer'],
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

    public function failedValidation(Validator $validator)
    {
        CustomResponse::failValidation($validator);
    }
}
