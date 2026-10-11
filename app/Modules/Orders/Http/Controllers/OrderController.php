<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Modules\Orders\Actions\UpdateOrderStatus;
use App\Modules\Orders\Enums\OrderStatusEnum;
use App\Modules\Orders\Http\Requests\UpdateOrderStatusRequest;
use App\Modules\Orders\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $allowedSortable = ['updated_at'];

        // Listado del panel: órdenes de todos los usuarios, no solo las del admin logueado
        $query = Order::query()->forAdmin()->latest('created_at');

        // Filtros dinámicos
        if ($request->filled('key')) {
            $query->where('key', $request->input('key'));
        }

        return $this->paginated(
            $query,
            $request,
            $allowedSortable,
        );
}

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOrderRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Order $order)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOrderRequest $request, Order $order)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order)
    {
        //
    }

    public function updateStatus(
        UpdateOrderStatusRequest $request,
        string $id,
        UpdateOrderStatus $updateOrderStatus,
    ): JsonResponse {
        $order = $updateOrderStatus->handle(
            Order::findForAdminOrFail($id),
            OrderStatusEnum::from($request->validated('status')),
        );

        return response()->json($order);
    }

    // Sin route model binding: el binding aplica el scope de dueño y al admin le daba 404
    public function downloadOrderTicket(string $id)
    {
        $order = Order::findForAdminOrFail($id);
        $filename = "ticket-{$order->id}.pdf";

        $disk = Storage::disk('public');

        if ($order->pdf_path && $disk->exists($order->pdf_path)) {
            return response()->download($disk->path($order->pdf_path), $filename);
        }

        return Pdf::loadView('orders::ticket', compact('order'))
            ->setPaper('a5')
            ->download($filename);
    }
}
