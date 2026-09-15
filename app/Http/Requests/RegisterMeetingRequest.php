<?php

namespace App\Http\Requests;

use App\Custom\CustomResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class RegisterMeetingRequest extends FormRequest
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
            'doctor_id' => ['required', 'integer', 'exists:doctors,id'],
            'scheduled_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:pending,confirmed,cancelled'],
            'start_date' => ['required', 'string'],
            'end_date' => ['required', 'string'],
            'title' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        $language = $this->query('lang');

        return [
            'required' => CustomResponse::responseValidation('required', $language),
            'integer' => CustomResponse::responseValidation('integer', $language),
            'date_format' => CustomResponse::responseValidation('date_format', $language),
            'min' => CustomResponse::responseValidation('min', $language),
            'max' => CustomResponse::responseValidation('max', $language),
            'exists' => CustomResponse::responseValidation('exists', $language),
            'in' => CustomResponse::responseValidation('in', $language),
            'string' => CustomResponse::responseValidation('string', $language),
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        CustomResponse::failValidation($validator);
    }
}
