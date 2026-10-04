<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_theme_reviews', function (Blueprint $table) {
            $table->id('id_student_theme_review');
            $table->unsignedBigInteger('id_client');
            $table->integer('id_theme');
            $table->unsignedInteger('repetitions')->default(0);
            $table->unsignedInteger('interval_days')->default(0);
            $table->decimal('easiness_factor', 5, 2)->default(2.50);
            $table->unsignedTinyInteger('last_quality')->nullable();
            $table->dateTime('initialized_at')->nullable();
            $table->dateTime('last_reviewed_at')->nullable();
            $table->dateTime('next_review_at')->nullable();
            $table->timestamps();
            $table->unique(['id_client', 'id_theme'], 'uq_student_theme_review');
            $table->index(['id_client', 'next_review_at'], 'idx_str_client_due');
            $table->foreign('id_client', 'fk_str_client')
                ->references('id_client')
                ->on('clients')
                ->cascadeOnDelete();
            $table->foreign('id_theme', 'fk_str_theme')
                ->references('id_theme')
                ->on('themes')
                ->cascadeOnUpdate();
        });

        $legacyReviews = DB::table('student_objective_reviews as sor')
            ->join(
                'learning_objectives as lo',
                'lo.id_learning_objective',
                '=',
                'sor.id_learning_objective'
            )
            ->select('sor.*', 'lo.id_theme')
            ->orderBy('sor.id_student_objective_review')
            ->get()
            ->groupBy(fn ($review) => $review->id_client.'-'.$review->id_theme);

        foreach ($legacyReviews as $reviews) {
            $review = $reviews->sortBy('next_review_at')->first();

            DB::table('student_theme_reviews')->insert([
                'id_client' => $review->id_client,
                'id_theme' => $review->id_theme,
                'repetitions' => $review->repetitions,
                'interval_days' => $review->interval_days,
                'easiness_factor' => $review->easiness_factor,
                'last_quality' => $review->last_quality,
                'initialized_at' => $reviews->min('initialized_at'),
                'last_reviewed_at' => $reviews->max('last_reviewed_at'),
                'next_review_at' => $review->next_review_at,
                'created_at' => $reviews->min('created_at'),
                'updated_at' => $reviews->max('updated_at'),
            ]);
        }

        Schema::table('smart_review_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('id_student_theme_review')
                ->nullable()
                ->after('id_smart_review_assignment');
        });

        DB::statement(
            'UPDATE smart_review_assignments AS sra
             INNER JOIN student_objective_reviews AS sor
                ON sor.id_student_objective_review = sra.id_student_objective_review
             INNER JOIN learning_objectives AS lo
                ON lo.id_learning_objective = sor.id_learning_objective
             INNER JOIN student_theme_reviews AS str
                ON str.id_client = sor.id_client
               AND str.id_theme = lo.id_theme
             SET sra.id_student_theme_review = str.id_student_theme_review'
        );

        if (DB::table('smart_review_assignments')->whereNull('id_student_theme_review')->exists()) {
            throw new RuntimeException('Existen asignaciones que no pudieron asociarse a un tema.');
        }

        DB::table('smart_review_assignments')
            ->select('id_student_theme_review', 'scheduled_for')
            ->groupBy('id_student_theme_review', 'scheduled_for')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(function ($duplicate) {
                $ids = DB::table('smart_review_assignments')
                    ->where('id_student_theme_review', $duplicate->id_student_theme_review)
                    ->whereDate('scheduled_for', $duplicate->scheduled_for)
                    ->orderBy('id_smart_review_assignment')
                    ->pluck('id_smart_review_assignment');

                DB::table('smart_review_assignments')
                    ->whereIn('id_smart_review_assignment', $ids->slice(1))
                    ->delete();
            });

        Schema::table('smart_review_assignments', function (Blueprint $table) {
            $table->dropForeign('fk_sra_objective_review');
            $table->dropUnique('uq_sra_objective_review_date');
            $table->dropColumn('id_student_objective_review');
        });

        Schema::table('smart_review_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('id_student_theme_review')->nullable(false)->change();
            $table->unique(
                ['id_student_theme_review', 'scheduled_for'],
                'uq_sra_theme_review_date'
            );
            $table->foreign('id_student_theme_review', 'fk_sra_theme_review')
                ->references('id_student_theme_review')
                ->on('student_theme_reviews')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('smart_review_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('id_student_objective_review')
                ->nullable()
                ->after('id_smart_review_assignment');
        });

        DB::statement(
            'UPDATE smart_review_assignments AS sra
             INNER JOIN student_theme_reviews AS str
                ON str.id_student_theme_review = sra.id_student_theme_review
             SET sra.id_student_objective_review = (
                SELECT MIN(sor.id_student_objective_review)
                FROM student_objective_reviews AS sor
                INNER JOIN learning_objectives AS lo
                    ON lo.id_learning_objective = sor.id_learning_objective
                WHERE sor.id_client = str.id_client
                  AND lo.id_theme = str.id_theme
             )'
        );

        if (DB::table('smart_review_assignments')->whereNull('id_student_objective_review')->exists()) {
            throw new RuntimeException(
                'No se puede revertir: existen temas sin un estado histórico por objetivo.'
            );
        }

        Schema::table('smart_review_assignments', function (Blueprint $table) {
            $table->dropForeign('fk_sra_theme_review');
            $table->dropUnique('uq_sra_theme_review_date');
            $table->dropColumn('id_student_theme_review');
        });

        Schema::table('smart_review_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('id_student_objective_review')->nullable(false)->change();
            $table->unique(
                ['id_student_objective_review', 'scheduled_for'],
                'uq_sra_objective_review_date'
            );
            $table->foreign('id_student_objective_review', 'fk_sra_objective_review')
                ->references('id_student_objective_review')
                ->on('student_objective_reviews')
                ->cascadeOnDelete();
        });

        Schema::drop('student_theme_reviews');
    }
};
