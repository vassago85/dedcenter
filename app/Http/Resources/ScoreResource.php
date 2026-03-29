<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shooter_id' => $this->shooter_id,
            'gong_id' => $this->gong_id,
            'is_hit' => $this->is_hit,
            'device_id' => $this->device_id,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
            'synced_at' => $this->synced_at?->toIso8601String(),
        ];
    }
}
