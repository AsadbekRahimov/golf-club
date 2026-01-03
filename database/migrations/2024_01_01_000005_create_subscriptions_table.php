<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('client_id')
                  ->constrained('clients')
                  ->cascadeOnDelete();
            $table->foreignId('booking_request_id')
                  ->nullable()
                  ->constrained('booking_requests')
                  ->nullOnDelete();
            
            $table->string('subscription_type', 20);
            
            $table->foreignId('locker_id')
                  ->nullable()
                  ->constrained('lockers')
                  ->nullOnDelete();
            
            $table->date('start_date');
            $table->date('end_date')->nullable();
            
            $table->decimal('price', 10, 2);
            
            $table->string('status', 20)->default('active');
            
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            
            $table->timestamps();
            
            $table->index('subscription_type');
            $table->index('status');
            $table->index('end_date');
            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
