<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('booking_request_id')
                  ->unique()
                  ->constrained('booking_requests')
                  ->cascadeOnDelete();
            $table->foreignId('client_id')
                  ->constrained('clients')
                  ->cascadeOnDelete();
            
            $table->decimal('amount', 10, 2);
            
            $table->string('receipt_file_path', 500)->nullable();
            $table->string('receipt_file_name', 255)->nullable();
            $table->string('receipt_file_type', 50)->nullable();
            
            $table->string('status', 20)->default('pending');
            
            $table->foreignId('verified_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            
            $table->timestamps();
            
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
