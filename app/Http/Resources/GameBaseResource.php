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
            'genres' => GenreResource::collection($this->whenLoaded('genres')),
        ];
    }
}
