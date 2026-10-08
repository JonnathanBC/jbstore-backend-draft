<?php

namespace App\Modules\Drivers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Drivers\Http\Requests\StoreDriverRequest;
use App\Modules\Drivers\Http\Requests\UpdateDriverRequest;
use App\Modules\Drivers\Http\Resources\DriverResource;
use App\Modules\Drivers\Models\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function index(Request $request)
    {
        $allowedSortable = ['updated_at'];
        $query = Driver::query()->with('user');

        return $this->paginated(
            $query,
            $request,
            $allowedSortable,
            DriverResource::class,
        );
    }

    public function store(StoreDriverRequest $request): JsonResponse
    {
        $driver = Driver::create($request->validated());

        return response()->json($driver, 201);
    }

    public function show(Driver $driver): JsonResponse
    {
        return response()->json($driver);
    }

    public function update(UpdateDriverRequest $request, Driver $driver): JsonResponse
    {
        $driver->update($request->validated());

        return response()->json($driver->fresh());
    }

    public function destroy(Driver $driver): JsonResponse
    {
        $driver->delete();

        return response()->json(null, 204);
    }
}
