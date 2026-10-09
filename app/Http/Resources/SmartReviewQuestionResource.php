<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmartReviewQuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_question,
            'id_theme' => $this->theme_uuid,
            'theme' => $this->theme,
            'id_exam_type' => $this->id_exam_type,
            'question' => $this->question,
            'image' => $this->image,
            'alternatives' => [
                'a' => $this->alt_a,
                'b' => $this->alt_b,
                'c' => $this->alt_c,
                'd' => $this->alt_d,
                'e' => $this->alt_e,
            ],
            'response' => $this->response,
            'distractor_analysis' => $this->distractor_analysis,
            'justification' => $this->justification,
            'reference' => $this->reference,
        ];
    }
}
