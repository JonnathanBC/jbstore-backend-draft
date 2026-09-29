<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Modelo "Amazon": las direcciones no tienen tipo; cada usuario tiene una sola predeterminada.
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'type']);
            $table->dropColumn('type');
            // Postgres no indexa FKs automáticamente.
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->string('type', 20)->default('shipping');
            $table->index(['user_id', 'type']);
        });
    }
};
