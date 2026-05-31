<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Option extends Model
{
    use HasFactory;

    protected $fillable = [
        "name",
        "type"
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'option_product')
            ->using(OptionProduct::class)
            ->withPivot('features')
            ->withTimestamps();
    }

    public function features()
    {
        return $this->hasMany(Feature::class);
    }
}
