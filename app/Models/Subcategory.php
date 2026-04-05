<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subcategory extends Model
{
    use HasFactory;

    // Relation 1:n
    public function products()
    {
        return $this->hasMany(Product::class);
    }


    // Relation 1:n inverse
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
