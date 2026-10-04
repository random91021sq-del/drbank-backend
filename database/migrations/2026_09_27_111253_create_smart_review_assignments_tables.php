<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('smart_review_assignments', function (Blueprint $table) {
            $table->id('id_smart_review_assignment');
            $table->unsignedBigInteger('id_client');
            $table->unsignedBigInteger('id_learning_objective');
            $table->date('scheduled_for');
            $table->string('status', 20)->default('pending');
            $table->timestamp('generated_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

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
        });

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
            $table->foreign(
                'id_smart_review_assignment',
                'fk_sraq_assignment'
            )
                ->references('id_smart_review_assignment')
                ->on('smart_review_assignments')
                ->cascadeOnDelete();
            $table->foreign('id_question', 'fk_sraq_question')
                ->references('id_question')
                ->on('questions')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('smart_review_assignment_questions');
        Schema::dropIfExists('smart_review_assignments');
    }
};
