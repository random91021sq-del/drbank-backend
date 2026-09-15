<?php

namespace App\Http\Requests;

use App\Custom\CustomResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ExamTypeRequest extends FormRequest
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
            'year'=>'required|string',
            'exam'=>'required|string'
        ];
    }
    public function messages()
    {
        $language=$this->query('lang');
        return [
            'alpha'=>CustomResponse::responseValidation('alpha', $language),
            'required'=> CustomResponse::responseValidation('required', $language),
            'string'=>CustomResponse::responseValidation('string', $language),
            'size'=>CustomResponse::responseValidation('size', $language),
        ];
    }
    public function failedValidation(Validator $validator)
    {
        CustomResponse::failValidation($validator);
    }
}
