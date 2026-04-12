<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Option extends Model
{
    use HasFactory;

    #[Fillable(['name', 'type'])]
    protected $fillable = [
        "name",
        "type"
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'option_product')
            ->withPivot('value')
            ->withTimestamps();
    }

    public function features()
    {
        return $this->hasMany(Feature::class);
    }
}
