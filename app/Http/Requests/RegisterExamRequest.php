<?php

namespace App\Http\Requests;

use App\Custom\CustomResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class RegisterExamRequest extends FormRequest
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
            'exam_type' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'total_questions' => ['required', 'integer'],
            'started_at' => ['required', 'date']
        ];
    }

    public function messages(): array
    {
        $language = $this->query('lang');

        return [
            'alpha' => CustomResponse::responseValidation('lang', $language),
            'size' => CustomResponse::responseValidation('size', $language),
            'required' => CustomResponse::responseValidation('required', $language),
            'integer' => CustomResponse::responseValidation('integer', $language),
            'date' => CustomResponse::responseValidation('date', $language),
        ];
    }

    public function failedValidation(Validator $validator)
    {
        CustomResponse::failValidation($validator);
    }
}
