<?php

namespace App\Http\Resources;

use App\Support\UserRank;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameCopyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        //return parent::toArray($request);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'region' => $this->region,
            'purchase_price' => $this->purchase_price,
            'purchase_date' => $this->purchase_date,
            'review_id' => $this->review?->id,
            'play_status' => $this->review?->play_status ?? 'backlog',
            'rating' => $this->review?->rating,
            'hours_played' => $this->review?->hours_played,
            'notes' => $this->review?->notes,
            'playthrough_count' => $this->review?->playthrough_count,
            'would_replay' => $this->review?->would_replay,
            'would_recommend' => $this->review?->would_recommend,
            'game' => new GameBaseResource(
                $this->whenLoaded('game')
            ),
            'platform' => new PlatformResource(
                $this->whenLoaded('platform')
            ),
            'parts' => CopyPartResource::collection(
                $this->whenLoaded('parts')
            ),
            'user' => $this->whenLoaded('user', fn() => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar' => $this->user->avatar,
                'rank' => UserRank::fromCount($this->user->game_copies_count ?? 0),
                'copy_count' => $this->user->game_copies_count,
            ]),
        ];
    }
}
