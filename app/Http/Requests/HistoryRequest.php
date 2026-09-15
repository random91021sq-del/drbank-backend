<?php

namespace App\Http\Requests;

use App\Custom\CustomResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class HistoryRequest extends FormRequest
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
            'page' => ['required', 'integer'],
            'limit' => ['required', 'integer'],
            'groupBy' => ['required', 'in:specialty,theme,area']
        ];
    }
    public function messages()
    {
        $language = $this->query('lang');
        return [
            'required' => CustomResponse::responseValidation('required', $language),
            'alpha' => CustomResponse::responseValidation('lang', $language),
            'size' => CustomResponse::responseValidation('size', $language),
            'integer' => CustomResponse::responseValidation('integer', $language),
            'in' => CustomResponse::responseValidation('in', $language),
        ];
    }
    public function failedValidation(Validator $validator)
    {
        CustomResponse::failValidation($validator);
    }
}
