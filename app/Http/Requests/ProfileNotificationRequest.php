<?php

namespace App\Http\Requests;

use App\Custom\CustomResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ProfileNotificationRequest extends FormRequest
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
            'notifications' => ['required', 'numeric', 'digits:' . env('DIGITS_NOTIFICATION'), 'in:' . env('IN_NOTIFICATION')]
        ];
    }
    public function messages()
    {
        $language = $this->query('lang');
        return [
            'alpha' => CustomResponse::responseValidation('alpha', $language),
            'size' => CustomResponse::responseValidation('size', $language),
            'required' => CustomResponse::responseValidation('required', $language),
            'numeric' => CustomResponse::responseValidation('numeric', $language),
            'digits' => CustomResponse::responseValidation('digits', $language),
            'in' => CustomResponse::responseValidation('betweenIn', $language)
        ];
    }

    public function passedValidation()
    {
        $language = $this->query('lang');
        $validator = Validator([], []);
        if (is_string($this->notifications)) {
            $validator->errors()->add('notifications', CustomResponse::responseValidation('notString', $language));
        }
        if ($validator->errors()->isNotEmpty()) {
            $this->failedValidation($validator);
        }
    }
    
    public function failedValidation(Validator $validator)
    {
        CustomResponse::failValidation($validator);
    }
}
