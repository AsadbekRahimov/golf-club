<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_requests', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('client_id')
                  ->constrained('clients')
                  ->cascadeOnDelete();
            
            $table->string('service_type', 20);
            $table->string('game_subscription_type', 20)->nullable();
            $table->unsignedInteger('locker_duration_months')->nullable();
            
            $table->decimal('total_price', 10, 2);
            
            $table->string('status', 30)->default('pending');
            
            $table->text('admin_notes')->nullable();
            
            $table->foreignId('processed_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            
            $table->timestamps();
            
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_requests');
    }
};
