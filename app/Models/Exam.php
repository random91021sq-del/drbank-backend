<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    protected $table = 'exams';

    protected $primaryKey = 'id_exam';

    protected $fillable = ['id_client', 'exam_type', 'smart_review_stage', 'title', 'total_questions', 'correct_answers', 'incorrect_answers', 'empty_answers', 'score_percentage', 'time_spent', 'exam_summary', 'recommendation', 'started_at', 'completed_at', 'status'];

    public $timestamps = false;

    public function getStartedAtAttribute(?string $value)
    {
        return $value
            ? Carbon::parse($value)->setTimezone('America/Lima')->format('d/m/Y H:i:s')
            : null;
    }

    public function getCompletedAtAttribute(?string $value)
    {
        return $value
            ? Carbon::parse($value)->setTimezone('America/Lima')->format('d/m/Y H:i:s')
            : null;
    }

    protected $casts = [
        'exam_summary' => 'json',
    ];

    protected function examSummary(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                $summary = json_decode($value, true) ?? [];

                if (empty($summary)) {
                    return [];
                }

                $questionIds = array_column($summary, 'question_id');

                $questionsData = Question::query()
                    ->join('themes as t', 't.id_theme', '=', 'questions.id_theme')
                    ->select([
                        'questions.id_question',
                        't.uuid as theme_uuid',
                        't.theme',
                        'questions.question',
                        'questions.alt_a',
                        'questions.alt_b',
                        'questions.alt_c',
                        'questions.alt_d',
                        'questions.alt_e',
                        'questions.response as correct_answer',
                        'questions.justification',
                        'questions.reference',
                        'questions.distractor_analysis',
                    ])
                    ->whereIn('questions.id_question', array_unique($questionIds))
                    ->get()
                    ->keyBy('id_question');

                foreach ($summary as &$item) {
                    $item['difficulty'] = $item['difficulty'] ?? null;
                    $id = $item['question_id'] ?? null;
                    if ($id && isset($questionsData[$id])) {
                        $item['id_theme'] = $questionsData[$id]->theme_uuid;
                        $item['theme'] = $questionsData[$id]->theme;
                        $item['question'] = $questionsData[$id]->question;
                        $item['alt_a'] = $questionsData[$id]->alt_a;
                        $item['alt_b'] = $questionsData[$id]->alt_b;
                        $item['alt_c'] = $questionsData[$id]->alt_c;
                        $item['alt_d'] = $questionsData[$id]->alt_d;
                        $item['alt_e'] = $questionsData[$id]->alt_e;
                        $item['correct_answer'] = $questionsData[$id]->correct_answer;
                        $item['justification'] = $questionsData[$id]->justification;
                        $item['reference'] = $questionsData[$id]->reference;
                        $item['distractor_analysis'] = $questionsData[$id]->distractor_analysis;
                    } else {
                        $item['id_theme'] = null;
                        $item['theme'] = null;
                        $item['justification'] = null;
                        $item['reference'] = null;
                        $item['distractor_analysis'] = null;
                        $item['question'] = null;
                        $item['alt_a'] = null;
                        $item['alt_b'] = null;
                        $item['alt_c'] = null;
                        $item['alt_d'] = null;
                        $item['alt_e'] = null;
                        $item['correct_answer'] = null;
                    }
                }

                return $summary;
            }
        );
    }
}
