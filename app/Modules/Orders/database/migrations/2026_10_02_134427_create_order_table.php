<?php

use App\Modules\Orders\Enums\OrderStatusEnum;
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
        Schema::create('order', function (Blueprint $table) {
            $table->uuid();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('pdf_path')->nullable();
            $table->json('content');
            $table->json('address');

            $table->string('payment_provider')->default('niubiz');
            $table->string('payment_id')->nullable();
            $table->float('total');
            $table->enum('status', OrderStatusEnum::values())
                ->default(OrderStatusEnum::Pending->value);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order');
    }
};
