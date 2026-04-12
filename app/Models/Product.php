<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        "sku",
        "name",
        "description",
        "image_path",
        "image_path",
        "price",
        "subcategory_id",
    ];

    // Relations 1:n
    public function variants()
    {
        return $this->hasMany(Variant::class);
    }

    // Relations 1:n inverse
    public function subcategory()
    {
        return $this->belongsTo(\App\Modules\Categories\Models\Subcategory::class);
    }

    // Relations n:n
    public function options()
    {
        return $this->belongsToMany(Option::class)
            ->withPivot('value')
            ->withTimestamps();
    }
}
