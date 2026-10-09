<?php

use App\Modules\Payments\Enums\PaymentStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            // Lo genera el servidor. UNIQUE = última defensa contra cobros duplicados
            $table->string('purchase_number', 12)->unique();

            // Foto del total al iniciar el pago: lo que se cobra es esto, no lo que mande el cliente
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('PEN');

            $table->enum('status', PaymentStatusEnum::values())
                ->default(PaymentStatusEnum::Pending->value)
                ->index();

            $table->string('provider')->default('niubiz');
            $table->string('provider_transaction_id')->nullable()->unique();

            // Una orden por pago, como máximo
            $table->foreignUuid('order_id')->nullable()->unique()->constrained()->restrictOnDelete();

            // Solo lo que la pasarela devuelve enmascarado: nunca la tarjeta completa
            $table->string('card_masked')->nullable();
            $table->string('card_brand')->nullable();
            $table->string('error_message')->nullable();

            // `json` (no jsonb) conserva el orden de las claves: el replay devuelve lo mismo
            $table->json('provider_response')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
