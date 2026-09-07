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
    Schema::create('rentals', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
        $table->string('rental_code')->unique();
        $table->date('start_date');
        $table->date('end_date');
        $table->decimal('total_price', 12, 2);
        $table->enum('payment_status', ['unpaid', 'paid', 'refunded'])->default('unpaid');
        $table->enum('rental_status', ['pending', 'ready_for_pickup', 'ongoing', 'completed', 'cancelled'])->default('pending');
        $table->string('payment_proof')->nullable();
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rentals');
    }
};
