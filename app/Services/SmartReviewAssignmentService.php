<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SmartReviewAssignmentService
{
    public const QUESTIONS_PER_THEME = 20;

    public function generate(): array
    {
        $generated = 0;
        $skipped = 0;
        $clientIds = [];
        $endOfToday = now('America/Lima')->endOfDay();

        DB::table('student_theme_reviews as str')
            ->join('themes as t', 't.id_theme', '=', 'str.id_theme')
            ->where('t.status', 1)
            ->whereNotNull('str.next_review_at')
            ->where('str.next_review_at', '<=', $endOfToday)
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('study_block_themes as sbt')
                    ->join('study_blocks as sb', 'sb.id_study_block', '=', 'sbt.id_study_block')
                    ->whereColumn('sbt.id_theme', 'str.id_theme')
                    ->whereColumn('sbt.id_exam_type', 'str.id_exam_type')
                    ->whereColumn('sb.id_client', 'str.id_client')
                    ->where('sb.status', 'active');
            })
            ->select(
                'str.id_student_theme_review',
                'str.id_client',
                'str.id_theme',
                'str.id_exam_type',
                'str.next_review_at'
            )
            ->selectSub(function ($query) {
                $query->from('study_block_themes as selected_sbt')
                    ->join('study_blocks as selected_sb', 'selected_sb.id_study_block', '=', 'selected_sbt.id_study_block')
                    ->whereColumn('selected_sbt.id_theme', 'str.id_theme')
                    ->whereColumn('selected_sbt.id_exam_type', 'str.id_exam_type')
                    ->whereColumn('selected_sb.id_client', 'str.id_client')
                    ->where('selected_sb.status', 'active')
                    ->orderByDesc('selected_sb.id_study_block')
                    ->limit(1)
                    ->select('selected_sb.id_study_block');
            }, 'id_study_block')
            ->orderBy('str.id_client')
            ->orderBy('str.id_theme')
            ->chunkById(200, function ($reviews) use (&$generated, &$skipped, &$clientIds) {
                foreach ($reviews as $review) {
                    if ($this->generateForTheme($review)) {
                        $generated++;
                        $clientIds[(int) $review->id_client] = true;
                    } else {
                        $skipped++;
                    }
                }
            }, 'str.id_student_theme_review', 'id_student_theme_review');

        return [
            'generated' => $generated,
            'skipped' => $skipped,
            'client_ids' => array_keys($clientIds),
        ];
    }

    private function generateForTheme(object $review): bool
    {
        $scheduledFor = Carbon::parse($review->next_review_at, 'America/Lima')->toDateString();

        $exists = DB::table('smart_review_assignments')
            ->where('id_student_theme_review', $review->id_student_theme_review)
            ->whereDate('scheduled_for', $scheduledFor)
            ->exists();

        if ($exists) {
            return false;
        }

        $questions = DB::table('questions as q')
            ->join('themes as t', 't.id_theme', '=', 'q.id_theme')
            ->where('q.id_theme', $review->id_theme)
            ->where('q.id_exam_type', $review->id_exam_type)
            ->where('q.status', 1)
            ->where('t.status', 1)
            ->inRandomOrder()
            ->limit(self::QUESTIONS_PER_THEME)
            ->select(
                'q.id_question',
                't.uuid as theme_uuid',
                't.theme',
                'q.question',
                'q.image',
                'q.alt_a',
                'q.alt_b',
                'q.alt_c',
                'q.alt_d',
                'q.alt_e',
                'q.response',
                'q.distractor_analysis',
                'q.justification',
                'q.reference'
            )
            ->get();

        if ($questions->count() !== self::QUESTIONS_PER_THEME) {
            Log::warning('SmartReview: tema sin 20 preguntas activas para asignar', [
                'id_client' => $review->id_client,
                'id_theme' => $review->id_theme,
                'id_exam_type' => $review->id_exam_type,
                'available_questions' => $questions->count(),
            ]);

            return false;
        }

        $now = now('America/Lima');

        DB::table('smart_review_assignments')->insert([
            'id_student_theme_review' => $review->id_student_theme_review,
            'id_study_block' => $review->id_study_block,
            'scheduled_for' => $scheduledFor,
            'questions' => json_encode(
                $questions->map(fn ($question) => [
                    'question_id' => (int) $question->id_question,
                    'id_theme' => $question->theme_uuid,
                    'theme' => $question->theme,
                    'question' => $question->question,
                    'image' => $question->image,
                    'alternatives' => [
                        'a' => $question->alt_a,
                        'b' => $question->alt_b,
                        'c' => $question->alt_c,
                        'd' => $question->alt_d,
                        'e' => $question->alt_e,
                    ],
                    'response' => $question->response,
                    'distractor_analysis' => $question->distractor_analysis,
                    'justification' => $question->justification,
                    'reference' => $question->reference,
                ])->all(),
                JSON_UNESCAPED_UNICODE
            ),
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return true;
    }
}
