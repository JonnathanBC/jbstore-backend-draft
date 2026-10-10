<?php

namespace App\Modules\Shippings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Models\Order;
use App\Modules\Shippings\Actions\CreateShipping;
use App\Modules\Shippings\Http\Requests\CreateShippingRequest;
use App\Modules\Shippings\Http\Resources\ShippingsResource;
use App\Modules\Shippings\Models\Shipping;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    public function index(Request $request)
    {
        $allowedSortable = ['updated_at'];
        $query = Shipping::query()->with('driver.user');

        return $this->paginated(
            $query,
            $request,
            $allowedSortable,
            ShippingsResource::class,
        );
    }

    public function store(
        CreateShippingRequest $request,
        string $orderId,
        CreateShipping $createShipping,
    ): JsonResponse {
        $shipping = $createShipping->handle(
            Order::findForAdminOrFail($orderId),
            (int) $request->validated('driver_id'),
        );

        return (new ShippingsResource($shipping->refresh()->load('driver.user')))
            ->response()
            ->setStatusCode(201);
    }
}
