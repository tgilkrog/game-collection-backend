<?php

namespace App\Http\Resources;

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
            'game_base_id' => $this->game_base_id,
            'platform_id' => $this->platform_id,
            'game' => new GameBaseResource(
                $this->whenLoaded('game')
            ),
            'platform' => new PlatformResource(
                $this->whenLoaded('platform')
            ),
            'parts' => CopyPartResource::collection(
                $this->whenLoaded('parts')
            ),
        ];
    }
}
