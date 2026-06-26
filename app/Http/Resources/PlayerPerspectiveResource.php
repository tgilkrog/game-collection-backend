<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerPerspectiveResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'      => $this->id,
            'igdb_id' => $this->igdb_id,
            'name'    => $this->name,
            'slug'    => $this->slug,
        ];
    }
}
