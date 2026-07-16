<?php

namespace App\Modules\Products\Models;

use App\Modules\Products\Models\Variant;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory;

    protected $appends = ['image'];

    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->image_path ? Storage::url($this->image_path) : null,
        );
    }

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
        "stock",
        "subcategory_id",
    ];

    public function subcategory()
    {
        return $this->belongsTo(\App\Modules\Categories\Models\Subcategory::class);
    }

    public function variants()
    {
        return $this->hasMany(Variant::class);
    }

    public function options()
    {
        return $this->belongsToMany(Option::class)
            ->using(OptionProduct::class)
            ->withPivot('features')
            ->withTimestamps();
    }
}
