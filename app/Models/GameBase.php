<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

class GameBase extends Model
{
    protected $fillable = [
        'title',
        'release_year',
        'publisher',
        'developer',
        'description',
        'cover_image'
    ];

    protected static function booted () {
        static::deleting(function ($gameBase) {
            $filePath = public_path($gameBase->cover_image);
            if ($gameBase->cover_image && File::exists($filePath)) {
                File::delete($filePath);
            }
        });
    }

    public function game_copies() {
        return $this->hasMany(GameCopy::class);
    }

    public function genres()
    {
        return $this->belongsToMany(Genre::class, 'game_genre', 'game_base_id', 'genre_id');
    }
}
