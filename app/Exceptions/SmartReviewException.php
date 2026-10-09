<?php

namespace App\Exceptions;

use App\Custom\CustomResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class SmartReviewException extends RuntimeException
{
    public function __construct(
        public readonly string $messageKey,
        public readonly int $status,
        public readonly ?string $language,
        public readonly array $replace = []
    ) {
        parent::__construct($messageKey);
    }

    public function response(): JsonResponse
    {
        return CustomResponse::responseMessage(
            $this->messageKey,
            $this->status,
            $this->language,
            $this->replace
        );
    }
}
