<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Option extends Model
{
    use HasFactory;

    protected $fillable = [
        "name",
        "type"
    ];

    // Relations n:n
    public function produtcs()
    {
        return $this->belongsToMany(Product::class)
            ->withPivot('value')
            ->withTimestamps();
    }

    // Relations 1:n
    public function features()
    {
        return $this->hasMany(Feature::class);
    }
}
