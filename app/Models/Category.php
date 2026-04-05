<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    // Relation 1:n inverse
    public function subcategories()
    {
        return $this->hasMany(Subcategory::class);
    }

    // Relation 1:n inverse
    public function family()
    {
        return $this->belongsTo(Family::class);
    }
}
