<?php

namespace App\Modules\Categories\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Family extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function categories()
    {
        return $this->hasMany(Category::class);
    }
}
