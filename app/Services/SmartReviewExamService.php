<?php

namespace App\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SmartReviewExamService
{
    public function blockBelongsToClient(int $idStudyBlock, int $idClient): bool
    {
        return DB::table('study_blocks')
            ->where('id_study_block', $idStudyBlock)
            ->where('id_client', $idClient)
            ->exists();
    }

    public function paginateCompleted(
        int $idClient,
        int $idStudyBlock,
        string $stage,
        int $perPage
    ): LengthAwarePaginator {
        $exams = DB::table('exams')
            ->where('id_client', $idClient)
            ->where('id_study_block', $idStudyBlock)
            ->where('smart_review_stage', $stage)
            ->where('status', 'completed')
            ->select(
                'id_exam',
                'uuid',
                'smart_review_stage',
                'title',
                'total_questions',
                'score_percentage',
                'time_spent',
                'exam_summary',
                'started_at',
                'completed_at',
                'status'
            )
            ->orderByDesc('id_exam')
            ->paginate($perPage);

        $examItems = $exams->getCollection();
        $questionIdsByExam = $examItems->mapWithKeys(function ($exam) {
            $questionIds = collect(json_decode($exam->exam_summary ?? '[]', true) ?: [])
                ->pluck('question_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            return [(int) $exam->id_exam => $questionIds];
        });
        $examTypeByQuestion = DB::table('questions')
            ->whereIn('id_question', $questionIdsByExam->flatten()->unique()->values())
            ->pluck('id_exam_type', 'id_question');

        $items = $examItems->map(function ($exam) use (
            $questionIdsByExam,
            $examTypeByQuestion
        ) {
            $summary = collect(json_decode($exam->exam_summary ?? '[]', true) ?: []);
            $correctAnswers = $summary
                ->filter(fn ($answer) => ($answer['correct'] ?? false) === true)
                ->count();
            $answeredQuestions = $summary
                ->filter(fn ($answer) => trim((string) ($answer['response'] ?? '')) !== '')
                ->count();
            $totalQuestions = (int) $exam->total_questions;
            $examType = $questionIdsByExam
                ->get((int) $exam->id_exam, collect())
                ->map(fn ($idQuestion) => $examTypeByQuestion->get($idQuestion))
                ->filter()
                ->first();

            return [
                'id_exam' => (int) $exam->id_exam,
                'uuid' => $exam->uuid,
                'exam_type' => $examType,
                'stage' => $exam->smart_review_stage,
                'title' => $exam->title,
                'total_questions' => $totalQuestions,
                'correct_answers' => $correctAnswers,
                'incorrect_answers' => max(0, $answeredQuestions - $correctAnswers),
                'unanswered_questions' => max(0, $totalQuestions - $answeredQuestions),
                'score_percentage' => $exam->score_percentage !== null
                    ? (float) $exam->score_percentage
                    : null,
                'time_spent' => $exam->time_spent !== null
                    ? (int) $exam->time_spent
                    : null,
                'started_at' => $exam->started_at,
                'completed_at' => $exam->completed_at,
                'status' => $exam->status,
            ];
        })->values();

        return $exams->setCollection($items);
    }

    public function findPendingPosttest(int $idClient, int $idStudyBlock): ?array
    {
        $posttest = DB::table('exams')
            ->where('id_client', $idClient)
            ->where('id_study_block', $idStudyBlock)
            ->where('smart_review_stage', 'posttest')
            ->where('status', 'in_progress')
            ->latest('id_exam')
            ->select('id_exam', 'uuid', 'exam_summary')
            ->first();

        if (! $posttest) {
            return null;
        }

        $questionIds = collect(json_decode($posttest->exam_summary, true))
            ->pluck('question_id')
            ->map(fn ($id) => (int) $id)
            ->values();
        $questions = DB::table('questions as q')
            ->join('themes as t', 't.id_theme', '=', 'q.id_theme')
            ->whereIn('q.id_question', $questionIds)
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
                'q.id_exam_type',
                'q.response',
                'q.distractor_analysis',
                'q.justification',
                'q.reference'
            )
            ->get()
            ->keyBy('id_question');
        $responseQuestions = $questionIds->map(function ($idQuestion) use ($questions) {
            $question = $questions->get($idQuestion);

            return [
                'id_question' => $question->id_question,
                'id_theme' => $question->theme_uuid,
                'theme' => $question->theme,
                'id_exam_type' => $question->id_exam_type,
                'question' => $question->question,
                'image' => $question->image,
                'response' => $question->response,
                'distractor_analysis' => $question->distractor_analysis,
                'justification' => $question->justification,
                'reference' => $question->reference,
                'alternatives' => [
                    'a' => $question->alt_a,
                    'b' => $question->alt_b,
                    'c' => $question->alt_c,
                    'd' => $question->alt_d,
                    'e' => $question->alt_e,
                ],
            ];
        })->values();

        return [
            'id_exam' => $posttest->id_exam,
            'uuid' => $posttest->uuid,
            'id_study_block' => $idStudyBlock,
            'type' => 'posttest',
            'processing_status' => 'ready',
            'total_questions' => $responseQuestions->count(),
            'questions' => $responseQuestions,
        ];
    }
}
