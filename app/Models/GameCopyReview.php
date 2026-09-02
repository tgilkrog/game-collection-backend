<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameCopyReview extends Model
{
    const PLAY_STATUSES = ['playing', 'on_hold', 'completed', 'completionist', 'abandoned'];

    const PLAY_STATUS_LABELS = [
        'playing' => 'Playing',
        'on_hold' => 'On Hold',
        'completed' => 'Completed',
        'completionist' => '100%',
        'abandoned' => 'Abandoned',
    ];

    protected $fillable = [
        'user_id',
        'game_copy_id',
        'game_base_id',
        'play_status',
        'rating',
        'hours_played',
        'notes',
        'playthrough_count',
        'would_replay',
        'would_recommend',
    ];

    protected $casts = [
        'rating' => 'integer',
        'hours_played' => 'decimal:1',
        'playthrough_count' => 'integer',
        'would_replay' => 'boolean',
        'would_recommend' => 'boolean',
    ];

    public function copy()
    {
        return $this->belongsTo(GameCopy::class, 'game_copy_id');
    }

    public function game()
    {
        return $this->belongsTo(GameBase::class, 'game_base_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
