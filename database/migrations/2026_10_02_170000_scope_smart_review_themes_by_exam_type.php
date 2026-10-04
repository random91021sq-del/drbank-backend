<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('study_block_themes', function (Blueprint $table) {
            $table->string('id_exam_type', 50)
                ->collation('utf8mb4_general_ci')
                ->nullable()
                ->after('id_theme');
        });

        Schema::table('student_theme_reviews', function (Blueprint $table) {
            $table->string('id_exam_type', 50)
                ->collation('utf8mb4_general_ci')
                ->nullable()
                ->after('id_theme');
            $table->dropUnique('uq_student_theme_review');
        });

        DB::table('study_block_themes')->orderBy('id_study_block_theme')->each(function ($blockTheme) {
            $examType = $this->examTypeFromBlockQuestions(
                (int) $blockTheme->id_study_block,
                (int) $blockTheme->id_theme
            ) ?? DB::table('questions')
                ->where('id_theme', $blockTheme->id_theme)
                ->where('status', 1)
                ->orderBy('id_question')
                ->value('id_exam_type');

            if ($examType === null) {
                throw new RuntimeException(
                    "No se pudo determinar el tipo de examen del tema {$blockTheme->id_theme}."
                );
            }

            DB::table('study_block_themes')
                ->where('id_study_block_theme', $blockTheme->id_study_block_theme)
                ->update(['id_exam_type' => $examType]);
        });

        DB::table('student_theme_reviews')->orderBy('id_student_theme_review')->each(function ($review) {
            $pairs = DB::table('study_block_themes as sbt')
                ->join('study_blocks as sb', 'sb.id_study_block', '=', 'sbt.id_study_block')
                ->where('sb.id_client', $review->id_client)
                ->where('sbt.id_theme', $review->id_theme)
                ->select('sbt.id_exam_type')
                ->distinct()
                ->pluck('id_exam_type')
                ->filter()
                ->values();

            if ($pairs->isEmpty()) {
                $fallback = DB::table('questions')
                    ->where('id_theme', $review->id_theme)
                    ->where('status', 1)
                    ->orderBy('id_question')
                    ->value('id_exam_type');
                $pairs = collect([$fallback])->filter();
            }

            if ($pairs->isEmpty()) {
                throw new RuntimeException(
                    "No se pudo determinar el tipo de examen de la revisión {$review->id_student_theme_review}."
                );
            }

            DB::table('student_theme_reviews')
                ->where('id_student_theme_review', $review->id_student_theme_review)
                ->update(['id_exam_type' => $pairs->first()]);

            foreach ($pairs->slice(1) as $examType) {
                $attributes = (array) $review;
                unset($attributes['id_student_theme_review']);
                $attributes['id_exam_type'] = $examType;
                $newReviewId = DB::table('student_theme_reviews')->insertGetId($attributes);

                $blockIds = DB::table('study_block_themes as sbt')
                    ->join('study_blocks as sb', 'sb.id_study_block', '=', 'sbt.id_study_block')
                    ->where('sb.id_client', $review->id_client)
                    ->where('sbt.id_theme', $review->id_theme)
                    ->where('sbt.id_exam_type', $examType)
                    ->pluck('sbt.id_study_block');

                DB::table('smart_review_assignments')
                    ->where('id_student_theme_review', $review->id_student_theme_review)
                    ->whereIn('id_study_block', $blockIds)
                    ->update(['id_student_theme_review' => $newReviewId]);
            }
        });

        Schema::table('study_block_themes', function (Blueprint $table) {
            $table->string('id_exam_type', 50)
                ->collation('utf8mb4_general_ci')
                ->nullable(false)
                ->change();
            $table->index(['id_theme', 'id_exam_type'], 'idx_sbt_theme_exam_type');
        });

        Schema::table('student_theme_reviews', function (Blueprint $table) {
            $table->string('id_exam_type', 50)
                ->collation('utf8mb4_general_ci')
                ->nullable(false)
                ->change();
            $table->unique(
                ['id_client', 'id_theme', 'id_exam_type'],
                'uq_student_theme_review_exam_type'
            );
            $table->index(['id_theme', 'id_exam_type'], 'idx_str_theme_exam_type');
        });
    }

    public function down(): void
    {
        Schema::table('student_theme_reviews', function (Blueprint $table) {
            $table->dropUnique('uq_student_theme_review_exam_type');
            $table->dropIndex('idx_str_theme_exam_type');
            $table->dropColumn('id_exam_type');
            $table->unique(['id_client', 'id_theme'], 'uq_student_theme_review');
        });

        Schema::table('study_block_themes', function (Blueprint $table) {
            $table->dropIndex('idx_sbt_theme_exam_type');
            $table->dropColumn('id_exam_type');
        });
    }

    private function examTypeFromBlockQuestions(int $idStudyBlock, int $idTheme): ?string
    {
        $summaries = DB::table('exams')
            ->where('id_study_block', $idStudyBlock)
            ->whereNotNull('exam_summary')
            ->orderBy('id_exam')
            ->pluck('exam_summary');

        foreach ($summaries as $summary) {
            $questionIds = collect(json_decode($summary, true) ?: [])
                ->pluck('question_id')
                ->filter()
                ->map(fn ($id) => (int) $id);
            $examType = DB::table('questions')
                ->whereIn('id_question', $questionIds)
                ->where('id_theme', $idTheme)
                ->value('id_exam_type');
            if ($examType !== null) {
                return (string) $examType;
            }
        }

        return null;
    }
};
