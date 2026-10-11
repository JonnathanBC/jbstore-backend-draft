<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Google no entrega documento ni teléfono: el usuario nace con el perfil incompleto
 * y lo completa en el checkout (StartPayment lo exige antes de cobrar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('last_name')->nullable()->change();
            $table->string('document_type')->nullable()->change();
            $table->string('document_number')->nullable()->change();
            // Postgres permite varios NULL en una columna unique
            $table->string('phone')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('last_name')->nullable(false)->change();
            $table->string('document_type')->nullable(false)->change();
            $table->string('document_number')->nullable(false)->change();
            $table->string('phone')->nullable(false)->change();
        });
    }
};
