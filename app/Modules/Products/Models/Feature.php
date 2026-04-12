<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    use HasFactory;

    protected $fillable = [
        "value",
        "option_id",
        "description",
    ];

    public function option()
    {
        return $this->belongsTo(Option::class);
    }

    public function variants()
    {
        return $this->belongsToMany(Variant::class, 'feature_variant')
            ->withTimestamps();
    }
}
