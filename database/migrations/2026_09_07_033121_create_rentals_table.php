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
        $table->enum('rental_status', ['pending', 'ready_for_pickup', 'ongoing', 'completed', 'cancelled', 'damaged', 'lost'])->default('pending');
        $table->string('payment_proof')->nullable();
        $table->string('ktp_number')->nullable();
        $table->text('pickup_notes')->nullable();
        $table->text('return_notes')->nullable();
        $table->dateTime('pickup_date')->nullable();
        $table->dateTime('return_date')->nullable();
        $table->decimal('late_fee', 12, 2)->default(0);
        $table->boolean('is_damage')->default(false);
        $table->boolean('is_lost')->default(false);
        $table->text('damage_note')->nullable();
        $table->text('lost_note')->nullable();
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
