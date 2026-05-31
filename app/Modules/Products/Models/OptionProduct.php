<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class OptionProduct extends Pivot
{
    use HasFactory;

    protected $fillable = ['product_id', 'option_id', 'features'];

    protected $casts = [
        "features" => "array",
    ];
}
