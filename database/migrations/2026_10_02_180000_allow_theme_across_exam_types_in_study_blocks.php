<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('study_block_themes', function (Blueprint $table) {
            $table->index('id_study_block', 'idx_sbt_study_block');
            $table->dropUnique('uq_study_block_theme');
            $table->unique(
                ['id_study_block', 'id_theme', 'id_exam_type'],
                'uq_study_block_theme_exam_type'
            );
        });
    }

    public function down(): void
    {
        Schema::table('study_block_themes', function (Blueprint $table) {
            $table->dropUnique('uq_study_block_theme_exam_type');
            $table->unique(['id_study_block', 'id_theme'], 'uq_study_block_theme');
            $table->dropIndex('idx_sbt_study_block');
        });
    }
};
