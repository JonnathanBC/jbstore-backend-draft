<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    use HasFactory;

    protected $fillable = [
        "value",
        "option_id",
        "description",
    ];

    // Relations 1:n inverse
    public function option()
    {
        return $this->belongsTo(Option::class);
    }

    // Relations n:n
    public function variants()
    {
        return $this->belongsToMany(Variant::class)
            ->withTimestamps();
    }
}
