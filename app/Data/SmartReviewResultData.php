<?php

namespace App\Data;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

readonly class SmartReviewResultData
{
    public function __construct(
        public int $idExam,
        public string $uuid,
        public string $title,
        public int $idAssignment,
        public int $idTheme,
        public int $correctAnswers,
        public float $scorePercentage,
        public int $quality,
        public CarbonInterface $nextReviewAt,
        public Collection $answeredQuestions
    ) {}
}
