<?php

namespace App\Http\Requests;

use App\Custom\CustomResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class QuestionByThemeRequest extends FormRequest
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
             'id'=>['required', 'string','max:'.env('MAX_UUID_THEME')]
        ];
    }
     public function messages()
   {
        $language = $this->query('lang');
        return [
            'alpha' => CustomResponse::responseValidation('lang', $language),
            'size' => CustomResponse::responseValidation('size', $language),
            'required' => CustomResponse::responseValidation('required', $language),
            'string' => CustomResponse::responseValidation('string', $language),
            'max' => CustomResponse::responseValidation('max', $language)
        ];
    }
    public function failedValidation(Validator $validator)
    {
        CustomResponse::failValidation($validator);
    }
}
