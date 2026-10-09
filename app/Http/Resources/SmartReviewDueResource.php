<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmartReviewDueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $today = now('America/Lima')->startOfDay();
        $scheduledFor = Carbon::parse(
            $this->next_review_at,
            'America/Lima'
        )->startOfDay();

        return [
            'id_smart_review_assignment' => $this->id_smart_review_assignment,
            'id_student_theme_review' => (int) $this->id_student_theme_review,
            'id_study_block' => $this->id_study_block !== null
                ? (int) $this->id_study_block
                : null,
            'id_theme' => $this->theme_uuid,
            'theme' => $this->theme,
            'id_exam_type' => $this->id_exam_type,
            'initialized_at' => $this->toDate($this->initialized_at),
            'last_reviewed_at' => $this->last_reviewed_at
                ? $this->toDate($this->last_reviewed_at)
                : null,
            'next_review_at' => $this->toDate($this->next_review_at),
            'scheduled_for' => $scheduledFor->toDateString(),
            'review_status' => match (true) {
                $scheduledFor->lt($today) => 'overdue',
                $scheduledFor->isSameDay($today) => 'active',
                default => 'upcoming',
            },
            'questions_available' => $this->id_smart_review_assignment !== null,
        ];
    }

    private function toDate(mixed $value): string
    {
        return Carbon::parse($value, 'America/Lima')->toDateString();
    }
}
