<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmartReviewBlockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $block = $this->resource['block'];
        $blockExams = $this->resource['exams'];
        $blockThemes = $this->resource['themes'];
        $progress = $this->resource['progress'];
        $pretest = $blockExams->firstWhere('smart_review_stage', 'pretest');
        $posttest = $blockExams->firstWhere('smart_review_stage', 'posttest');
        $examTypes = $blockThemes->pluck('id_exam_type')->unique()->values();
        $totalReviews = (int) ($progress->total_reviews ?? 0);
        $completedReviews = (int) ($progress->completed_reviews ?? 0);

        return [
            'id_study_block' => $block->id_study_block,
            'status' => $block->status,
            'exam_type' => $examTypes->count() === 1 ? $examTypes->first() : null,
            'exam_types' => $examTypes,
            'pretest_score_percentage' => $pretest !== null
                ? (float) $pretest->score_percentage
                : null,
            'pretest_completed_at' => $block->pretest_completed_at,
            'posttest_score_percentage' => $posttest !== null
                ? (float) $posttest->score_percentage
                : null,
            'posttest_completed_at' => $block->posttest_completed_at,
            'posttest_available_at' => $block->posttest_available_at,
            'posttest_available' => ! empty($block->posttest_available_at)
                && $this->resource['now']->gte($block->posttest_available_at),
            'due_reviews' => $this->resource['due_reviews'],
            'total_reviews' => $totalReviews,
            'completed_reviews' => $completedReviews,
            'review_progress_percentage' => $totalReviews > 0
                ? round(($completedReviews / $totalReviews) * 100, 2)
                : 0.0,
            'exam_counts' => [
                'pretests' => $blockExams->where('smart_review_stage', 'pretest')->count(),
                'reviews' => $blockExams->where('smart_review_stage', 'review')->count(),
                'posttests' => $blockExams->where('smart_review_stage', 'posttest')->count(),
            ],
            'themes' => SmartReviewBlockThemeResource::collection($blockThemes)
                ->resolve($request),
        ];
    }
}
