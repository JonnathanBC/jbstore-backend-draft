<?php

namespace App\Concerns\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class OwnedByUserScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $userId = Auth::id();

        if ($userId === null) {
            return; // consola, jobs, seeders: sin usuario, sin filtro
        }

        $builder->where($model->qualifyColumn('user_id'), $userId);
    }
}
