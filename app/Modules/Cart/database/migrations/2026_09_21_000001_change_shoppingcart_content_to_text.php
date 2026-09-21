<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE shoppingcart
             ALTER COLUMN content TYPE text
             USING encode(content, 'base64')"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE shoppingcart
             ALTER COLUMN content TYPE bytea
             USING decode(content, 'base64')"
        );
    }
};
