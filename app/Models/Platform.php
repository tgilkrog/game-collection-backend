<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Platform extends Model
{
    protected $fillable = [
        'name',
        'alias',
        'manufacturer',
        'release_year'
    ];

    public function copies() {
        return $this->hasMany(GameCopy::class);
    }
}
