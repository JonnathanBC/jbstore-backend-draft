<?php

namespace App\Modules\Categories\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subcategory extends Model
{
    use HasFactory;

    #[Fillable(['name', 'category_id'])]
    protected $fillable = [
        "name",
        "category_id"
    ];

    public function products()
    {
        return $this->hasMany(\App\Models\Product::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
