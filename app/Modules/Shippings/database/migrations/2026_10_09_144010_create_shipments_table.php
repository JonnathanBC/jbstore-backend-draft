<?php

use App\Modules\Shippings\Enums\ShippingStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shippings', function (Blueprint $table) {
            $table->id();

            $table->foreignUuid('order_id')
                ->constrained()
                ->onDelete('cascade');

            $table->foreignId('driver_id')
                ->constrained()
                ->onDelete('cascade');

            $table->enum('status', ShippingStatusEnum::values())
                ->default(ShippingStatusEnum::Pending->value);

            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shippings');
    }
};
