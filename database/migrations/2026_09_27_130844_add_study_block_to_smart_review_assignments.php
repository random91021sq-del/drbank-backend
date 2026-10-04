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
            $table->unsignedBigInteger('id_study_block')
                ->nullable()
                ->after('id_student_objective_review');
        });

        DB::statement(
            'UPDATE smart_review_assignments AS sra
             INNER JOIN student_objective_reviews AS sor
                ON sor.id_student_objective_review = sra.id_student_objective_review
             INNER JOIN learning_objectives AS lo
                ON lo.id_learning_objective = sor.id_learning_objective
             SET sra.id_study_block = (
                SELECT MAX(sb.id_study_block)
                FROM study_block_themes AS sbt
                INNER JOIN study_blocks AS sb
                    ON sb.id_study_block = sbt.id_study_block
                WHERE sbt.id_theme = lo.id_theme
                  AND sb.id_client = sor.id_client
             )'
        );

        if (DB::table('smart_review_assignments')->whereNull('id_study_block')->exists()) {
            throw new RuntimeException('Existen asignaciones sin bloque de estudio relacionado.');
        }

        Schema::table('smart_review_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('id_study_block')->nullable(false)->change();
            $table->index('id_study_block', 'idx_sra_study_block');
            $table->foreign('id_study_block', 'fk_sra_study_block')
                ->references('id_study_block')
                ->on('study_blocks')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('smart_review_assignments', function (Blueprint $table) {
            $table->dropForeign('fk_sra_study_block');
            $table->dropIndex('idx_sra_study_block');
            $table->dropColumn('id_study_block');
        });
    }
};
