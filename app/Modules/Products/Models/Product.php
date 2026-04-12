<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    #[Fillable(['sku', 'name', 'description', 'image_path', 'price', 'subcategory_id'])]
    protected $fillable = [
        "sku",
        "name",
        "description",
        "image_path",
        "price",
        "subcategory_id",
    ];

    public function variants()
    {
        return $this->hasMany(Variant::class);
    }

    public function subcategory()
    {
        return $this->belongsTo(\App\Modules\Categories\Models\Subcategory::class);
    }

    public function options()
    {
        return $this->belongsToMany(Option::class)
            ->withPivot('value')
            ->withTimestamps();
    }
}
