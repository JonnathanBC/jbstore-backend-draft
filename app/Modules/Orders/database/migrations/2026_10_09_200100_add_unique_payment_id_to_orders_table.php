<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un pago de la pasarela = una orden. Si hay duplicados previos, esta migración falla: limpiarlos antes.
        Schema::table('orders', function (Blueprint $table) {
            $table->unique('payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['payment_id']);
        });
    }
};
