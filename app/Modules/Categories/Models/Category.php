<?php

namespace App\Modules\Categories\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    #[Fillable(['name', 'family_id'])]
    protected $fillable = [
        "name",
        "family_id",
    ];

    public function subcategories()
    {
        return $this->hasMany(Subcategory::class);
    }

    public function family()
    {
        return $this->belongsTo(Family::class);
    }
}
