<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class DispatchDueSmartReviewPosttestsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
       $endOfToday = now('America/Lima')->endOfDay();

        DB::table('study_blocks as sb')
            ->where('sb.status', 'active')
            ->whereNotNull('sb.pretest_completed_at')
            ->whereNotNull('sb.posttest_available_at')
            ->where('sb.posttest_available_at', '<=', $endOfToday)
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('exams as e')
                    ->whereColumn('e.id_client', 'sb.id_client')
                    ->whereColumn('e.id_study_block', 'sb.id_study_block')
                    ->where('e.smart_review_stage', 'posttest')
                    ->where('e.status', 'in_progress');
            })
            ->orderBy('sb.id_study_block')
            ->pluck('sb.id_study_block')
            ->each(fn ($id) => GenerateSmartReviewPosttestJob::dispatch((int) $id));
    }
}
