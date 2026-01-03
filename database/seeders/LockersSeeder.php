<?php

namespace Database\Seeders;

use App\Enums\LockerStatus;
use App\Models\Locker;
use Illuminate\Database\Seeder;

class LockersSeeder extends Seeder
{
    public function run(): void
    {
        $totalLockers = 50;

        for ($i = 1; $i <= $totalLockers; $i++) {
            Locker::updateOrCreate(
                ['locker_number' => str_pad($i, 3, '0', STR_PAD_LEFT)],
                ['status' => LockerStatus::AVAILABLE]
            );
        }
    }
}
