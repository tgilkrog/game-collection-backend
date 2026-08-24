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
            'notes' => $this->notes,
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
