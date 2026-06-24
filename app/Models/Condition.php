<?php

namespace App\Models;

use App\Models\CopyPart;
use Illuminate\Database\Eloquent\Model;

class Condition extends Model
{
    protected $fillable = [
        'name'
    ];

    public function copyParts()
    {
        return $this->hasMany(CopyPart::class);
    }
}