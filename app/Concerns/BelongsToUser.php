<?php

namespace App\Concerns;

use App\Concerns\Scopes\OwnedByUserScope;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToUser
{
    public static function bootBelongsToUser(): void
    {
        /** @var class-string<Model>|string $model */
        $model = static::class;

        $model::addGlobalScope(new OwnedByUserScope);

        $model::creating(function (Model $model) {
            if ($model->user_id === null && Auth::check()) {
                $model->user_id = Auth::id();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
