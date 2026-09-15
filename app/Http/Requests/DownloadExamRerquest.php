<?php

namespace App\Http\Requests;

use App\Custom\CustomResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class DownloadExamRerquest extends FormRequest
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
            'exams' => ['required', 'array'],
            'exams.*'=>['required','string']
        ];
    }
    public function messages():array
     {
        $language = $this->query('lang');
        return [
            'alpha' => CustomResponse::responseValidation('lang', $language),
            'required' => CustomResponse::responseValidation('required', $language),
            'string' => CustomResponse::responseValidation('string', $language)
        ];
    }

    public function failedValidation(Validator $validator)
    {
        CustomResponse::failValidation($validator);
    }
}
