<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('smart_review_assignments', function (Blueprint $table) {
            $table->json('questions')->nullable()->after('scheduled_for');
        });

        DB::table('smart_review_assignments')
            ->orderBy('id_smart_review_assignment')
            ->each(function ($assignment) {
                $questions = DB::table('smart_review_assignment_questions')
                    ->where('id_smart_review_assignment', $assignment->id_smart_review_assignment)
                    ->orderBy('position')
                    ->pluck('id_question')
                    ->map(fn ($idQuestion) => ['question_id' => (int) $idQuestion])
                    ->values()
                    ->all();

                DB::table('smart_review_assignments')
                    ->where('id_smart_review_assignment', $assignment->id_smart_review_assignment)
                    ->update([
                        'questions' => json_encode($questions, JSON_UNESCAPED_UNICODE),
                    ]);
            });

        Schema::drop('smart_review_assignment_questions');

        Schema::table('smart_review_assignments', function (Blueprint $table) {
            $table->dropColumn('generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('smart_review_assignments', function (Blueprint $table) {
            $table->timestamp('generated_at')->nullable()->after('status');
        });

        DB::table('smart_review_assignments')->update([
            'generated_at' => DB::raw('created_at'),
        ]);

        Schema::create('smart_review_assignment_questions', function (Blueprint $table) {
            $table->id('id_smart_review_assignment_question');
            $table->unsignedBigInteger('id_smart_review_assignment');
            $table->integer('id_question');
            $table->unsignedTinyInteger('position');
            $table->timestamps();
            $table->unique(
                ['id_smart_review_assignment', 'id_question'],
                'uq_sraq_assignment_question'
            );
            $table->unique(
                ['id_smart_review_assignment', 'position'],
                'uq_sraq_assignment_position'
            );
            $table->foreign('id_smart_review_assignment', 'fk_sraq_assignment')
                ->references('id_smart_review_assignment')
                ->on('smart_review_assignments')
                ->cascadeOnDelete();
            $table->foreign('id_question', 'fk_sraq_question')
                ->references('id_question')
                ->on('questions')
                ->cascadeOnDelete();
        });

        DB::table('smart_review_assignments')
            ->orderBy('id_smart_review_assignment')
            ->each(function ($assignment) {
                $now = now();
                $rows = collect(json_decode($assignment->questions, true) ?: [])
                    ->values()
                    ->map(fn ($question, $position) => [
                        'id_smart_review_assignment' => $assignment->id_smart_review_assignment,
                        'id_question' => (int) $question['question_id'],
                        'position' => $position + 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all();

                if ($rows !== []) {
                    DB::table('smart_review_assignment_questions')->insert($rows);
                }
            });

        Schema::table('smart_review_assignments', function (Blueprint $table) {
            $table->dropColumn('questions');
        });
    }
};
