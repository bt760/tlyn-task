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
        Schema::create(table: 'orders', callback:  function (Blueprint $table) {
            $table->id();
            $table->foreignId(column: 'user_id')->constrained()->cascadeOnDelete();
            $table->string(column: 'type')->index();
            $table->decimal(column: 'amount_gram', total: 10, places: 3);
            $table->decimal(column: 'remaining_amount_gram', total: 10, places: 3);
            $table->decimal(column: 'price_per_gram', total: 20, places: 0);
            $table->string(column: 'status')->index();
            $table->string(column: 'idempotency_key')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'orders');
    }
};
