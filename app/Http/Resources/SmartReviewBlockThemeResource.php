<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmartReviewBlockThemeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'theme' => $this->theme,
            'specialty' => $this->specialty,
            'area' => $this->area,
            'id_exam_type' => $this->id_exam_type,
        ];
    }
}
