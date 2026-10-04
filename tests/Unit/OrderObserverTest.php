<?php

namespace Tests\Unit;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Observers\OrderObserver;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class OrderObserverTest extends TestCase
{
    public function test_ticket_generation_failure_does_not_interrupt_order_creation(): void
    {
        $order = new Order;
        $order->id = 'order-id';

        $pdf = Mockery::mock(DomPdf::class);
        Pdf::shouldReceive('loadView')
            ->once()
            ->with('orders::ticket', ['order' => $order])
            ->andReturn($pdf);
        $pdf->shouldReceive('setPaper')->once()->with('a5')->andReturnSelf();
        $pdf->shouldReceive('save')
            ->once()
            ->andThrow(new RuntimeException('Ticket rendering failed'));

        Log::shouldReceive('error')
            ->once()
            ->with('Unable to generate order ticket', [
                'order_id' => 'order-id',
                'exception' => 'Ticket rendering failed',
            ]);

        (new OrderObserver)->created($order);

        $this->assertNull($order->pdf_path);
    }
}
