<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city');
            $table->string('province')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 2)->default('EC');
            $table->string('reference')->nullable();
            $table->string('phone', 20);
            $table->integer('receiver');
            $table->json('receiver_info');
            $table->boolean('is_default')->default(false);



            $table->timestamps();

            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
