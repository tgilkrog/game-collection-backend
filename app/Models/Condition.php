<?php

namespace App\Models;

use CopyPart;
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
