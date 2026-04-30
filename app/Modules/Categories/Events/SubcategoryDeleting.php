<?php

namespace App\Modules\Categories\Events;

use App\Modules\Categories\Models\Subcategory;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubcategoryDeleting
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Subcategory $subcategory
    ) {}
}
