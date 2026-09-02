<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameCopyReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'play_status' => $this->play_status,
            'rating' => $this->rating,
            'hours_played' => $this->hours_played,
            'notes' => $this->notes,
            'playthrough_count' => $this->playthrough_count,
            'would_replay' => $this->would_replay,
            'would_recommend' => $this->would_recommend,
            'created_at' => $this->created_at,
            'game' => new GameBaseResource($this->whenLoaded('game')),
        ];
    }
}
