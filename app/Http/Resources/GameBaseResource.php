<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\GameModeResource;
use App\Http\Resources\PlayerPerspectiveResource;
use App\Http\Resources\ThemeResource;

class GameBaseResource extends JsonResource
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
            'title' => $this->title,
            'release_year' => $this->release_year,
            'publisher' => $this->publisher,
            'developer' => $this->developer,
            'description' => $this->description,
            'cover_image' => $this->cover_image,
            'game_copies'         => GameCopyResource::collection($this->whenLoaded('game_copies')),
            'genres'              => GenreResource::collection($this->whenLoaded('genres')),
            'themes'              => ThemeResource::collection($this->whenLoaded('themes')),
            'game_modes'          => GameModeResource::collection($this->whenLoaded('game_modes')),
            'player_perspectives' => PlayerPerspectiveResource::collection($this->whenLoaded('playerPerspectives')),
        ];
    }
}
