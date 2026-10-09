<?php

namespace App\Services;

use App\Exceptions\SmartReviewException;
use Symfony\Component\HttpFoundation\Response;

class SmartReviewScheduleService
{
    public function calculateQuality(
        bool $isCorrect,
        string $difficulty,
        ?string $language
    ): int {
        $difficultyScore = match ($difficulty) {
            'hard' => 0,
            'regular' => 1,
            'easy' => 2,
            default => throw new SmartReviewException(
                'invalidDifficulty',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $language
            ),
        };

        return $difficultyScore + ($isCorrect ? 3 : 0);
    }

    public function calculateSchedule(object $review, int $quality): array
    {
        $easinessFactor = (float) $review->easiness_factor;
        $repetitions = (int) $review->repetitions;
        $intervalDays = (int) $review->interval_days;
        $easinessFactor += 0.1 - (5 - $quality) * (0.08 + (5 - $quality) * 0.02);
        $easinessFactor = max(1.3, $easinessFactor);

        if ($quality < 3) {
            return [0, 1, $easinessFactor];
        }

        $repetitions++;
        $intervalDays = match ($repetitions) {
            1 => 1,
            2 => 6,
            default => max(1, (int) round($intervalDays * $easinessFactor)),
        };

        return [$repetitions, $intervalDays, $easinessFactor];
    }
}
