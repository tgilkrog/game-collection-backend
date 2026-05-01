<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Genre extends Model
{
    protected $fillable = [
        'name',
        'slug'
    ];

    public function games()
    {
        return $this->belongsToMany(GameBase::class, 'game_genre');
    }
}
