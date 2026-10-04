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
            $table->unsignedBigInteger('id_student_objective_review')
                ->nullable()
                ->after('id_smart_review_assignment');
        });

        DB::statement(
            'UPDATE smart_review_assignments AS sra
             INNER JOIN student_objective_reviews AS sor
                ON sor.id_client = sra.id_client
               AND sor.id_learning_objective = sra.id_learning_objective
             SET sra.id_student_objective_review = sor.id_student_objective_review'
        );

        if (DB::table('smart_review_assignments')
            ->whereNull('id_student_objective_review')
            ->exists()) {
            throw new RuntimeException(
                'Existen asignaciones sin un student_objective_review relacionado.'
            );
        }

        Schema::table('smart_review_assignments', function (Blueprint $table) {
            $table->dropForeign(['id_client']);
            $table->dropForeign(['id_learning_objective']);
            $table->dropUnique('uq_sra_client_objective_date');
            $table->dropIndex('idx_sra_client_status_date');
            $table->dropColumn(['id_client', 'id_learning_objective']);
        });

        Schema::table('smart_review_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('id_student_objective_review')
                ->nullable(false)
                ->change();
            $table->json('questions')->nullable(false)->change();
            $table->unique(
                ['id_student_objective_review', 'scheduled_for'],
                'uq_sra_objective_review_date'
            );
            $table->index(
                ['status', 'scheduled_for'],
                'idx_sra_status_date'
            );
            $table->foreign(
                'id_student_objective_review',
                'fk_sra_objective_review'
            )
                ->references('id_student_objective_review')
                ->on('student_objective_reviews')
                ->cascadeOnDelete();
        });

        Schema::dropIfExists('student_question_reviews');
    }

    public function down(): void
    {
        Schema::create('student_question_reviews', function (Blueprint $table) {
            $table->id('id_student_question_review');
            $table->unsignedBigInteger('id_study_block');
            $table->integer('id_question');
            $table->unsignedInteger('repetitions')->default(0);
            $table->unsignedInteger('interval_days')->default(0);
            $table->decimal('easiness_factor', 5, 2)->default(2.50);
            $table->unsignedTinyInteger('last_quality')->nullable();
            $table->dateTime('first_reviewed_at')->nullable();
            $table->dateTime('last_reviewed_at')->nullable();
            $table->dateTime('next_review_at')->nullable();
            $table->timestamps();
            $table->unique(
                ['id_study_block', 'id_question'],
                'uq_study_block_question'
            );
            $table->index(
                ['id_study_block', 'next_review_at'],
                'idx_sqr_due'
            );
            $table->index('id_question', 'idx_sqr_question');
            $table->foreign('id_study_block', 'fk_sqr_study_block')
                ->references('id_study_block')
                ->on('study_blocks')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreign('id_question', 'fk_sqr_question')
                ->references('id_question')
                ->on('questions')
                ->cascadeOnUpdate();
        });

        Schema::table('smart_review_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('id_client')->nullable()->after('id_smart_review_assignment');
            $table->unsignedBigInteger('id_learning_objective')->nullable()->after('id_client');
        });

        DB::statement(
            'UPDATE smart_review_assignments AS sra
             INNER JOIN student_objective_reviews AS sor
                ON sor.id_student_objective_review = sra.id_student_objective_review
             SET sra.id_client = sor.id_client,
                 sra.id_learning_objective = sor.id_learning_objective'
        );

        Schema::table('smart_review_assignments', function (Blueprint $table) {
            $table->dropForeign('fk_sra_objective_review');
            $table->dropUnique('uq_sra_objective_review_date');
            $table->dropIndex('idx_sra_status_date');
            $table->unsignedBigInteger('id_client')->nullable(false)->change();
            $table->unsignedBigInteger('id_learning_objective')->nullable(false)->change();
            $table->json('questions')->nullable()->change();
            $table->unique(
                ['id_client', 'id_learning_objective', 'scheduled_for'],
                'uq_sra_client_objective_date'
            );
            $table->index(
                ['id_client', 'status', 'scheduled_for'],
                'idx_sra_client_status_date'
            );
            $table->foreign('id_client')
                ->references('id_client')
                ->on('clients')
                ->cascadeOnDelete();
            $table->foreign('id_learning_objective')
                ->references('id_learning_objective')
                ->on('learning_objectives')
                ->cascadeOnDelete();
            $table->dropColumn('id_student_objective_review');
        });
    }
};
