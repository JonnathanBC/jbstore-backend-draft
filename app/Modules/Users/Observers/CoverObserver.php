<?php

namespace App\Modules\Users\Observers;

use App\Modules\Users\Models\Cover;

class CoverObserver
{
    public function creating(Cover $cover): void
    {
        $cover->order = Cover::query()->max('order') + 1;
    }
}
