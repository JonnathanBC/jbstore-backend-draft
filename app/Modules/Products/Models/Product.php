<?php

namespace App\Modules\Products\Models;

use App\Modules\Products\Models\Variant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \App\Modules\Products\Factories\ProductFactory::new();
    }

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

    public function options()
    {
        return $this->belongsToMany(Option::class)
            ->withPivot('value')
            ->withTimestamps();
    }
}
