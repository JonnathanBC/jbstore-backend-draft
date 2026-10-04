<?php

namespace App\Modules\Orders\Observers;

use App\Modules\Orders\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrderObserver
{
    public function created(Order $order): void
    {
        try {
            $pdf = Pdf::loadView('orders::ticket', compact('order'))->setPaper('a5');

            $pdf->save(storage_path('app/public/tickets/ticket-' . $order->id . '.pdf'));

            $order->pdf_path = 'tickets/ticket-' . $order->id . '.pdf';
            $order->save();
        } catch (Throwable $exception) {
            Log::error('Unable to generate order ticket', [
                'order_id' => $order->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
