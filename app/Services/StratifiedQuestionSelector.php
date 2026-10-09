<?php

namespace App\Services;

use App\Exceptions\InsufficientStratifiedQuestionsException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StratifiedQuestionSelector
{
    /**
     * @param array<int, array{id_theme:int,id_exam_type:string,required:int}> $requirements
     */
    public function selectPairs(array $requirements, array $excludedQuestionIds = []): Collection
    {
        $result = collect();

        foreach ($requirements as $requirement) {
            $idTheme = (int) $requirement['id_theme'];
            $idExamType = (string) $requirement['id_exam_type'];
            $required = (int) $requirement['required'];
            $query = DB::table('questions as q')
                ->join('themes as t', 't.id_theme', '=', 'q.id_theme')
                ->where('q.id_theme', $idTheme)
                ->where('q.id_exam_type', $idExamType)
                ->where('q.status', 1)
                ->where('t.status', 1)
                ->when($excludedQuestionIds, fn ($query) => $query->whereNotIn('q.id_question', $excludedQuestionIds));

            $available = (clone $query)->count('q.id_question');
            if ($available < $required) {
                throw new InsufficientStratifiedQuestionsException(
                    'No hay suficientes preguntas activas para completar la selección.'
                );
            }

            $result->push($query->inRandomOrder()->limit($required)->select(
                'q.id_question', 'q.id_theme', 'q.id_exam_type', 't.uuid as theme_uuid',
                't.theme', 'q.question', 'q.image', 'q.alt_a', 'q.alt_b', 'q.alt_c',
                'q.alt_d', 'q.alt_e', 'q.response', 'q.distractor_analysis',
                'q.justification', 'q.reference'
            )->get());
        }

        return $result;
    }

    /**
     * Select random active questions for each requested theme.
     *
     * @param  array<int, int>  $requirements  [id_theme => required questions]
     * @param  array<int>  $excludedQuestionIds
     * @return Collection<int, Collection<int, object>>
     */
    public function select(
        array $requirements,
        array $excludedQuestionIds = [],
        ?string $idExamType = null
    ): Collection
    {
        $result = collect();

        foreach ($requirements as $idTheme => $required) {
            $idTheme = (int) $idTheme;
            $required = (int) $required;

            $query = DB::table('questions as q')
                ->join('themes as t', 't.id_theme', '=', 'q.id_theme')
                ->where('q.id_theme', $idTheme)
                ->when($idExamType !== null, fn ($query) => $query->where('q.id_exam_type', $idExamType))
                ->where('q.status', 1)
                ->where('t.status', 1)
                ->when(
                    $excludedQuestionIds,
                    fn ($query) => $query->whereNotIn('q.id_question', $excludedQuestionIds)
                );

            $available = (clone $query)->count('q.id_question');

            if ($available < $required) {
                throw new InsufficientStratifiedQuestionsException(
                    'No hay suficientes preguntas activas para completar la selección.'
                );
            }

            $questions = $query
                ->inRandomOrder()
                ->limit($required)
                ->select(
                    'q.id_question',
                    'q.id_theme',
                    't.uuid as theme_uuid',
                    't.theme',
                    'q.question',
                    'q.image',
                    'q.alt_a',
                    'q.alt_b',
                    'q.alt_c',
                    'q.alt_d',
                    'q.alt_e',
                    'q.response',
                    'q.distractor_analysis',
                    'q.justification',
                    'q.reference'
                )
                ->get();

            $result->put($idTheme, $questions);
        }

        return $result;
    }
}
