<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function paginated(
        Builder $query,
        Request $request,
        array $sortable = [],
    ): JsonResponse {
        foreach ((array) $request->input('order', []) as $field => $dir) {
            if (in_array($field, $sortable, true) && in_array($dir, ['asc', 'desc'], true)) {
                $query->orderBy($field, $dir);
            }
        }

        if (in_array($request->input('pagination'), ['false', '0'], true)) {
            return response()->json($query->get());
        }

        return response()->json(
            $query
                ->paginate($request->integer('per_page', 10))
                ->appends($request->query())
        );
    }
}
