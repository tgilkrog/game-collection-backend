<?php

namespace App\Models;

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
        'case_condition_id',
        'disc_condition_id',
        'manual_condition_id',
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

    public function caseCondition()
    {
        return $this->belongsTo(Condition::class, 'case_condition_id');
    }

    public function discCondition()
    {
        return $this->belongsTo(Condition::class, 'disc_condition_id');
    }

    public function manualCondition()
    {
        return $this->belongsTo(Condition::class, 'manual_condition_id');
    }
}
