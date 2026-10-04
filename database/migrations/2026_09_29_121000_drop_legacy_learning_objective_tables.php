<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('question_learning_objectives');
        Schema::dropIfExists('student_objective_reviews');
        Schema::dropIfExists('learning_objectives');
    }

    public function down(): void
    {
        Schema::create('learning_objectives', function (Blueprint $table) {
            $table->id('id_learning_objective');
            $table->integer('id_theme');
            $table->text('objective');
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->index('id_theme', 'idx_lo_theme');
        });

        $now = now('America/Lima');
        DB::table('themes')
            ->select('id_theme', 'theme', 'status')
            ->orderBy('id_theme')
            ->each(function ($theme) use ($now) {
                DB::table('learning_objectives')->insert([
                    'id_theme' => $theme->id_theme,
                    'objective' => 'Repaso general del tema: '.$theme->theme,
                    'sort_order' => 1,
                    'status' => $theme->status,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });

        Schema::create('question_learning_objectives', function (Blueprint $table) {
            $table->id('id_question_learning_objective');
            $table->integer('id_question');
            $table->unsignedBigInteger('id_learning_objective');
            $table->timestamps();
            $table->unique(
                ['id_question', 'id_learning_objective'],
                'uq_question_learning_objective'
            );
        });

        DB::table('questions as q')
            ->join('learning_objectives as lo', 'lo.id_theme', '=', 'q.id_theme')
            ->select('q.id_question', 'lo.id_learning_objective')
            ->orderBy('q.id_question')
            ->each(function ($association) use ($now) {
                DB::table('question_learning_objectives')->insert([
                    'id_question' => $association->id_question,
                    'id_learning_objective' => $association->id_learning_objective,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });

        Schema::create('student_objective_reviews', function (Blueprint $table) {
            $table->id('id_student_objective_review');
            $table->unsignedBigInteger('id_client');
            $table->unsignedBigInteger('id_learning_objective');
            $table->unsignedInteger('repetitions')->default(0);
            $table->unsignedInteger('interval_days')->default(0);
            $table->decimal('easiness_factor', 5, 2)->default(2.50);
            $table->unsignedTinyInteger('last_quality')->nullable();
            $table->dateTime('initialized_at')->nullable();
            $table->dateTime('last_reviewed_at')->nullable();
            $table->dateTime('next_review_at')->nullable();
            $table->timestamps();
            $table->unique(
                ['id_client', 'id_learning_objective'],
                'uq_student_objective_review'
            );
        });

        DB::table('student_theme_reviews as str')
            ->join('learning_objectives as lo', 'lo.id_theme', '=', 'str.id_theme')
            ->select('str.*', 'lo.id_learning_objective')
            ->orderBy('str.id_student_theme_review')
            ->each(function ($review) {
                DB::table('student_objective_reviews')->insert([
                    'id_client' => $review->id_client,
                    'id_learning_objective' => $review->id_learning_objective,
                    'repetitions' => $review->repetitions,
                    'interval_days' => $review->interval_days,
                    'easiness_factor' => $review->easiness_factor,
                    'last_quality' => $review->last_quality,
                    'initialized_at' => $review->initialized_at,
                    'last_reviewed_at' => $review->last_reviewed_at,
                    'next_review_at' => $review->next_review_at,
                    'created_at' => $review->created_at,
                    'updated_at' => $review->updated_at,
                ]);
            });
    }
};
