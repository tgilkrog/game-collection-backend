<?php

namespace App\Models;

use App\Models\CopyPart;
use Illuminate\Database\Eloquent\Model;

class GameCopy extends Model
{
    protected $fillable = [
        'title',
        'game_base_id',
        'platform_id',
        'region',
        'purchase_price',
        'purchase_date',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date'
    ];

    public function game() {
        return $this->belongsTo(GameBase::class, 'game_base_id');
    }

    public function platform() {
        return $this->belongsTo(Platform::class);
    }

    public function parts()
    {
        return $this->hasMany(CopyPart::class);
    }
}
