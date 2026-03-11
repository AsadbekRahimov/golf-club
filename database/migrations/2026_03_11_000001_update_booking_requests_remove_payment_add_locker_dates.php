<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_requests', function (Blueprint $table) {
            $table->dropColumn('total_price');
            $table->date('locker_start_date')->nullable()->after('locker_duration_months');
        });
    }

    public function down(): void
    {
        Schema::table('booking_requests', function (Blueprint $table) {
            $table->decimal('total_price', 10, 2)->default(0);
            $table->dropColumn('locker_start_date');
        });
    }
};
