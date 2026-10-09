<?php

namespace App\Http\Requests;

class CompleteSmartReviewPosttestRequest extends SmartReviewRequest
{
    protected function prepareForValidation(): void
    {
        if (! is_array($this->input('answers'))) {
            return;
        }

        $this->merge(['answers' => collect($this->input('answers'))->map(function ($answer) {
            if (! is_array($answer) || array_key_exists('id_question', $answer)) {
                return $answer;
            }

            $answer['id_question'] = $answer['question_id'] ?? $answer['id'] ?? $answer['idQuestion'] ?? null;

            return $answer;
        })->all()]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'time_spent' => ['required', 'integer', 'min:0'],
            'started_at' => ['required', 'date'],
            'completed_at' => ['required', 'date', 'after_or_equal:started_at'],
            'answers' => ['required', 'array', 'size:50'],
            'answers.*.id_question' => ['required', 'integer', 'distinct'],
            'answers.*.answer' => ['required', 'string', 'in:A,B,C,D,E,a,b,c,d,e'],
        ];
    }

    public function messages(): array
    {
        return $this->commonMessages();
    }
}
