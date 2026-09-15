<?php

namespace App\Http\Requests;

use App\Custom\CustomResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class ReportRequest extends FormRequest
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
            'questionId'=>['required','digits_between:'.env('DIGITS_REPORT_QUESTION')],
            'reason'=>['required','min:'.env('MIN_REPORT_REASON'),'max:'.env('MAX_REPORT_REASON')]
        ];
    }
    public function messages()
    {
        $language = $this->query('lang');
        return [
            'alpha' => CustomResponse::responseValidation('lang', $language),
            'size' => CustomResponse::responseValidation('size', $language),
            'required' => CustomResponse::responseValidation('required', $language),
            'digits_between' => CustomResponse::responseValidation('digits_between', $language),
            'min' => CustomResponse::responseValidation('min', $language),
            'max' => CustomResponse::responseValidation('max', $language)
        ];
    }
    public function failedValidation(Validator $validator)
    {
        CustomResponse::failValidation($validator);
    }
}
