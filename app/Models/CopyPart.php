<?php

use App\Models\Condition;
use App\Models\GameCopy;
use Illuminate\Database\Eloquent\Model;

class CopyPart extends Model
{
    protected $fillable = [
        'game_copy_id',
        'type', 
        'condition_id',
        'notes'
    ];

    public function gameCopy()
    {
        return $this->belongsTo(GameCopy::class);
    }

    public function condition()
    {
        return $this->belongsTo(Condition::class);
    }
}