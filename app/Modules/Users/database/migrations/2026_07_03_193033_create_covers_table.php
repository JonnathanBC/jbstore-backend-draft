<?php

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
        Schema::create('covers', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->string('image_path');
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);

            $table->datetime('start_at'); // fecha desde cuando queremos que tenga vigencia la portada
            $table->datetime('end_at')->nullable(); // fecha hasta cuando queremos que tenga vigencia la portada

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('covers');
    }
};
