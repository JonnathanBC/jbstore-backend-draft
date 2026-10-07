<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Concerns\Scopes\OwnedByUserScope;
use App\Modules\Orders\Enums\OrderStatusEnum;
use App\Modules\Orders\Http\Requests\UpdateOrderStatusRequest;
use App\Modules\Orders\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $allowedSortable = ['updated_at'];

        $query = Order::query()->latest('created_at');

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

    public function updateStatus(UpdateOrderStatusRequest $request, string $id): JsonResponse
    {
        // El admin opera sobre órdenes de cualquier usuario: sin el scope de dueño
        $order = Order::withoutGlobalScope(OwnedByUserScope::class)->findOrFail($id);
        $next = OrderStatusEnum::from($request->validated('status'));

        if (! $order->status->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => "No se puede pasar de {$order->status->value} a {$next->value}.",
            ]);
        }

        $order->status = $next;
        $order->save();

        return response()->json($order);
    }

    public function downloadOrderTicket(Order $order)
    {
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
