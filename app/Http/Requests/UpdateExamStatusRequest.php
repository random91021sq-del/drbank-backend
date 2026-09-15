<?php

namespace App\Http\Requests;

use App\Custom\CustomResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExamStatusRequest extends FormRequest
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
            'exam'=> ['required', 'string', 'uuid'],
            'status' => ['required', 'string', 'in:abandoned,completed'],
            'score_percentage' => ['required', 'numeric', 'between:0,100'],
            'time_spent' => ['required', 'integer'],
            'exam_summary' => ['required'],
            'completed_at' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        $language = $this->query('lang');

        return [
            'alpha' => CustomResponse::responseValidation('lang', $language),
            'size' => CustomResponse::responseValidation('size', $language),
            'required' => CustomResponse::responseValidation('required', $language),
            'string' => CustomResponse::responseValidation('string', $language),
            'in' => CustomResponse::responseValidation('in', $language),
            'uuid'=> CustomResponse::responseValidation('uuid', $language),
        ];
    }

    public function failedValidation(Validator $validator)
    {
        CustomResponse::failValidation($validator);
    }
}
