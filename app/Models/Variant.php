<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Variant extends Model
{
    use HasFactory;

    protected $fillable = [
        "sku",
        "image_path",
        "product_id",
    ];

    // Relation 1:n inverse
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Relations n:n
    public function features()
    {
        return $this->belongsToMany(Feature::class)
            ->withTimestamps();
    }
}
