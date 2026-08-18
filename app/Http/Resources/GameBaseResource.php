<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'game_copies' => $this->whenLoaded('game_copies', fn () => $this->game_copies->map(fn ($c) => [
                'id' => $c->id,
                'region' => $c->region,
                'purchase_price' => $c->purchase_price,
                'purchase_date' => $c->purchase_date,
                'notes' => $c->notes,
                'platform' => $c->platform ? ['id' => $c->platform->id, 'name' => $c->platform->name] : null,
                'parts' => $c->relationLoaded('parts') ? $c->parts->map(fn ($p) => [
                    'id' => $p->id,
                    'type' => $p->type,
                    'notes' => $p->notes,
                    'condition' => ['id' => $p->condition->id, 'name' => $p->condition->name],
                ])->values()->all() : [],
            ])->values()->all()
            ),
            'genres' => GenreResource::collection($this->whenLoaded('genres')),
            'themes' => ThemeResource::collection($this->whenLoaded('themes')),
            'game_modes' => GameModeResource::collection($this->whenLoaded('gameModes')),
            'player_perspectives' => PlayerPerspectiveResource::collection($this->whenLoaded('playerPerspectives')),
        ];
    }
}
