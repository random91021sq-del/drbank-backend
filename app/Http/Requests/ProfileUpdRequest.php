<?php

namespace App\Http\Requests;

use App\Custom\CustomResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:' . env('MIN_NAME'), 'max:' . env('MAX_NAME'), 'regex:/^[\pL\s]+$/u'],
            'last_name' => ['required', 'string', 'min:' . env('MIN_LAST_NAME'), 'max:' . env('MAX_LAST_NAME'), 'regex:/^[\pL\s]+$/u'],
            'phone' => ['numeric', 'gt:0', 'digits:' . env('DIGITS_PHONE')],
            'university'=>['string','alpha','min:'.env("MIN_UNIVERSITY"),'max:'.env("MAX_UNIVERSITY")]
        ];
    }
    public function messages()
   {
        $language = $this->query('lang');
        return [
            'lang.alpha' => CustomResponse::responseValidation('lang', $language),
            'alpha'=>CustomResponse::responseValidation('alpha',$language),
            'size' => CustomResponse::responseValidation('size', $language),
            'required' => CustomResponse::responseValidation('required', $language),
            'string' => CustomResponse::responseValidation('string', $language),
            'max' => CustomResponse::responseValidation('max', $language),
            'min' => CustomResponse::responseValidation('min', $language),
            'regex' => CustomResponse::responseValidation('regex', $language),
            'digits' => CustomResponse::responseValidation('digits', $language),
            'gt' => CustomResponse::responseValidation('gt', $language),
            'numeric' => CustomResponse::responseValidation('numeric', $language),
        ];
    }
    public function failedValidation(Validator $validator)
    {
        CustomResponse::failValidation($validator);
    }
}
