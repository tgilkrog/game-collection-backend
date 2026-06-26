<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerPerspective extends Model
{
    protected $fillable = ['igdb_id', 'name', 'slug'];

    public function gameBases()
    {
        return $this->belongsToMany(GameBase::class, 'game_base_player_perspective');
    }
}
