<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStratifiedQuestionsException extends RuntimeException
{
    public function __construct(
        public readonly int $idTheme,
        public readonly int $required,
        public readonly int $available
    ) {
        parent::__construct(
            "El tema {$idTheme} requiere {$required} preguntas activas, pero solo tiene {$available}."
        );
    }
}
