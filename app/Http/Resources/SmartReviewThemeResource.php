<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmartReviewThemeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'theme' => $this->theme,
            'id_exam_type' => $this->id_exam_type,
            'blocked' => (bool) $this->blocked,
        ];
    }
}
