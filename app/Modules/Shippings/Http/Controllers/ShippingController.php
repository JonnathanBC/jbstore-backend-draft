<?php

namespace App\Modules\Shippings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Models\Order;
use App\Modules\Shippings\Actions\CreateShipping;
use App\Modules\Shippings\Http\Requests\CreateShippingRequest;
use App\Modules\Shippings\Models\Shipping;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ShippingController extends Controller
{
    public function index(Request $request)
    {
        $allowedSortable = ['updated_at'];
        $query = Shipping::query();

        return $this->paginated(
            $query,
            $request,
            $allowedSortable
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

        return response()->json($shipping->refresh(), 201);
    }
}
