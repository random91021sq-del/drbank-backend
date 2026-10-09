<?php

namespace App\Http\Requests;

use App\Custom\CustomResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

abstract class SmartReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function language(): ?string
    {
        return $this->query('lang');
    }

    protected function commonMessages(): array
    {
        $language = $this->language();

        return collect([
            'required', 'string', 'integer', 'numeric', 'array', 'min', 'max',
            'size', 'uuid', 'date', 'after_or_equal', 'distinct', 'in',
        ])->mapWithKeys(fn (string $rule) => [
            $rule => CustomResponse::responseValidation($rule, $language),
        ])->all();
    }

    protected function failedValidation(Validator $validator): void
    {
        CustomResponse::failValidation($validator);
    }
}
