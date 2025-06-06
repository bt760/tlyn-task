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
        Schema::create(table: 'transactions', callback: function (Blueprint $table) {
            $table->id();
            $table->foreignId(column: 'buyer_id')->constrained(table: 'users');
            $table->foreignId(column: 'seller_id')->constrained(table: 'users');
            $table->foreignId(column: 'buy_order_id')->constrained(table: 'orders');
            $table->foreignId(column: 'sell_order_id')->constrained(table: 'orders');
            $table->decimal(column: 'amount_gram', total: 10, places: 3);
            $table->decimal(column: 'price_per_gram', total: 20, places: 0);
            $table->decimal(column: 'fee', total: 20, places: 0);
            $table->string(column: 'status', length: 20)->index();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'transactions');
    }
};
