<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmartReviewAssignmentQuestionsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $questions = collect(json_decode($this->questions ?? '[]', true) ?: [])
            ->map(function (array $question) {
                $question['id_question'] = (int) (
                    $question['id_question'] ?? $question['question_id']
                );
                $question['id_theme'] = $this->theme_uuid;
                $question['theme'] = $this->theme;
                $question['id_exam_type'] = $this->id_exam_type;
                unset($question['question_id']);

                return $question;
            })
            ->values();

        return [
            'id_study_block' => (int) $this->id_study_block,
            'id_smart_review_assignment' => (int) $this->id_smart_review_assignment,
            'id_student_theme_review' => (int) $this->id_student_theme_review,
            'id_theme' => $this->theme_uuid,
            'theme' => $this->theme,
            'id_exam_type' => $this->id_exam_type,
            'scheduled_for' => $this->scheduled_for,
            'questions' => $questions,
        ];
    }
}
