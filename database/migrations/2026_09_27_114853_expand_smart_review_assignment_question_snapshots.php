<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('smart_review_assignments')
            ->where('scheduled_for', '>', now('America/Lima')->toDateString())
            ->delete();

        DB::table('smart_review_assignments')
            ->orderBy('id_smart_review_assignment')
            ->each(function ($assignment) {
                $questionIds = collect(
                    json_decode($assignment->questions, true) ?: []
                )
                    ->pluck('question_id')
                    ->map(fn ($idQuestion) => (int) $idQuestion)
                    ->values();

                $questionsById = DB::table('questions')
                    ->whereIn('id_question', $questionIds)
                    ->select(
                        'id_question',
                        'question',
                        'image',
                        'alt_a',
                        'alt_b',
                        'alt_c',
                        'alt_d',
                        'alt_e',
                        'response',
                        'distractor_analysis',
                        'justification',
                        'reference'
                    )
                    ->get()
                    ->keyBy('id_question');

                $snapshots = $questionIds
                    ->map(function ($idQuestion) use ($questionsById) {
                        $question = $questionsById->get($idQuestion);

                        if (! $question) {
                            return null;
                        }

                        return [
                            'question_id' => (int) $question->id_question,
                            'question' => $question->question,
                            'image' => $question->image,
                            'alternatives' => [
                                'a' => $question->alt_a,
                                'b' => $question->alt_b,
                                'c' => $question->alt_c,
                                'd' => $question->alt_d,
                                'e' => $question->alt_e,
                            ],
                            'response' => $question->response,
                            'distractor_analysis' => $question->distractor_analysis,
                            'justification' => $question->justification,
                            'reference' => $question->reference,
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();

                DB::table('smart_review_assignments')
                    ->where('id_smart_review_assignment', $assignment->id_smart_review_assignment)
                    ->update([
                        'questions' => json_encode(
                            $snapshots,
                            JSON_UNESCAPED_UNICODE
                        ),
                        'updated_at' => now('America/Lima'),
                    ]);
            });
    }

    public function down(): void
    {
        DB::table('smart_review_assignments')
            ->orderBy('id_smart_review_assignment')
            ->each(function ($assignment) {
                $questionIds = collect(
                    json_decode($assignment->questions, true) ?: []
                )
                    ->map(fn ($question) => [
                        'question_id' => (int) $question['question_id'],
                    ])
                    ->values()
                    ->all();

                DB::table('smart_review_assignments')
                    ->where('id_smart_review_assignment', $assignment->id_smart_review_assignment)
                    ->update([
                        'questions' => json_encode(
                            $questionIds,
                            JSON_UNESCAPED_UNICODE
                        ),
                    ]);
            });
    }
};
