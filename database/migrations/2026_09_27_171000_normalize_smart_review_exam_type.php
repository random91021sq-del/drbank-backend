<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('exams')
            ->whereIn('smart_review_stage', ['pretest', 'review', 'posttest'])
            ->update([
                'exam_type' => 'smart review',
            ]);
    }

    public function down(): void
    {
        // El tipo anterior no puede reconstruirse de forma confiable.
    }
};
