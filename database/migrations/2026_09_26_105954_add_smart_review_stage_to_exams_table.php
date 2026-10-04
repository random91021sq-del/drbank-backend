<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->string('smart_review_stage', 20)
                ->nullable()
                ->after('exam_type')
                ->index();
        });

        DB::table('exams')
            ->whereIn('exam_type', ['pretest', 'posttest'])
            ->update([
                'smart_review_stage' => DB::raw('exam_type'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('smart_review_stage');
        });
    }
};
