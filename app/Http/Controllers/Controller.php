<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class Controller
{
    /**
     * @param  class-string<JsonResource>|null  $resource  Resource para transformar cada item.
     *         Se aplica item por item para mantener la forma del paginador de Laravel
     *         (data, current_page, last_page...) que espera el front.
     */
    protected function paginated(
        Builder $query,
        Request $request,
        array $sortable = [],
        ?string $resource = null,
    ): JsonResponse {
        foreach ((array) $request->input('order', []) as $field => $dir) {
            if (in_array($field, $sortable, true) && in_array($dir, ['asc', 'desc'], true)) {
                $query->orderBy($field, $dir);
            }
        }

        if ($request->has('pagination') && !$request->boolean('pagination')) {
            $items = $query->get();

            return response()->json([
                'data' => $resource
                    ? $resource::collection($items)->resolve($request)
                    : $items,
            ]);
        }

        $paginator = $query
            ->paginate($request->integer('per_page', 10))
            ->appends($request->query());

        if ($resource) {
            $paginator->through(fn ($item) => (new $resource($item))->resolve($request));
        }

        return response()->json($paginator);
    }
}
