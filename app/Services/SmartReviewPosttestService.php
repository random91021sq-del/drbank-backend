<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SmartReviewPosttestService
{
    private const SMART_REVIEW_EXAM_TYPE = 'smart review';

    private const MIN_THEMES_PER_BLOCK = 4;

    private const MAX_THEMES_PER_BLOCK = 20;

    private const QUESTIONS_PER_POSTTEST = 50;

    public function __construct(
        private readonly StratifiedQuestionSelector $questionSelector
    ) {}

    public function generate(int $idStudyBlock): array
    {
        return DB::transaction(function () use ($idStudyBlock) {
            $block = DB::table('study_blocks')
                ->where('id_study_block', $idStudyBlock)
                ->where('status', 'active')
                ->select('id_client', 'pretest_completed_at', 'posttest_available_at')
                ->lockForUpdate()
                ->first();

            if (! $block || empty($block->pretest_completed_at)) {
                throw new \RuntimeException('POSTTEST_BLOCK_NOT_READY');
            }
            
            $endOfToday = now('America/Lima')->endOfDay();

            if (empty($block->posttest_available_at) || Carbon::parse($block->posttest_available_at,'America/Lima')->gt($endOfToday)) {
                throw new \RuntimeException('POSTTEST_NOT_AVAILABLE');
            }

            $existingExamId = DB::table('exams')
                ->where('id_client', $block->id_client)
                ->where('id_study_block', $idStudyBlock)
                ->where('smart_review_stage', 'posttest')
                ->where('status', 'in_progress')
                ->value('id_exam');

            if ($existingExamId) {
                return [
                    'id_exam' => (int) $existingExamId,
                    'id_client' => (int) $block->id_client,
                    'created' => false,
                ];
            }

            $blockThemes = DB::table('study_block_themes as sbt')
                ->join('themes as t', 't.id_theme', '=', 'sbt.id_theme')
                ->where('sbt.id_study_block', $idStudyBlock)
                ->where('t.status', 1)
                ->orderBy('sbt.id_study_block_theme')
                ->select('t.id_theme', 'sbt.id_exam_type')
                ->get();

            $themeIds = $blockThemes->pluck('id_theme');

            if (
                $themeIds->count() < self::MIN_THEMES_PER_BLOCK
                || $themeIds->count() > self::MAX_THEMES_PER_BLOCK
            ) {
                throw new \RuntimeException('INVALID_BLOCK_THEMES');
            }

            $baseQuestions = intdiv(self::QUESTIONS_PER_POSTTEST, $themeIds->count());
            $remainder = self::QUESTIONS_PER_POSTTEST % $themeIds->count();
            $requirements = $blockThemes->values()->map(
                fn ($theme, $index) => [
                    'id_theme' => (int) $theme->id_theme,
                    'id_exam_type' => (string) $theme->id_exam_type,
                    'required' => $baseQuestions + ($index < $remainder ? 1 : 0),
                ]
            )->all();
            $questionIds = $this->questionSelector
                ->selectPairs($requirements)
                ->flatten(1)
                ->pluck('id_question')
                ->shuffle()
                ->values();

            $now = now();

            $idExam = (int) DB::table('exams')->insertGetId([
                'id_client' => $block->id_client,
                'id_study_block' => $idStudyBlock,
                'uuid' => (string) Str::uuid(),
                'exam_type' => self::SMART_REVIEW_EXAM_TYPE,
                'smart_review_stage' => 'posttest',
                'title' => null,
                'total_questions' => $questionIds->count(),
                'score_percentage' => null,
                'time_spent' => null,
                'exam_summary' => json_encode(
                    $questionIds->shuffle()->map(fn ($id) => [
                        'question_id' => (int) $id,
                    ])->values(),
                    JSON_UNESCAPED_UNICODE
                ),
                'status' => 'in_progress',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return [
                'id_exam' => $idExam,
                'id_client' => (int) $block->id_client,
                'created' => true,
            ];
        });
    }
}
