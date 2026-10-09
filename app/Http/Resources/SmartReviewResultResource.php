<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmartReviewResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_exam' => $this->idExam,
            'uuid' => $this->uuid,
            'title' => $this->title,
            'id_smart_review_assignment' => $this->idAssignment,
            'id_theme' => $this->idTheme,
            'correct_answers' => $this->correctAnswers,
            'total_questions' => $this->answeredQuestions->count(),
            'score_percentage' => $this->scorePercentage,
            'quality' => $this->quality,
            'next_review_at' => $this->nextReviewAt->toDateTimeString(),
            'results' => $this->answeredQuestions
                ->map(fn (array $question) => [
                    'id_question' => $question['question_id'],
                    'student_answer' => $question['student_answer'],
                    'correct_answer' => $question['response'],
                    'correct' => $question['correct'],
                    'difficulty' => $question['difficulty'],
                    'quality' => $question['quality'],
                    'justification' => $question['justification'],
                    'distractor_analysis' => $question['distractor_analysis'],
                    'reference' => $question['reference'],
                ])
                ->values(),
        ];
    }
}
