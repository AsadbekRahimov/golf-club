<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ExportService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function __construct(
        protected ExportService $exportService
    ) {}

    public function clients(Request $request)
    {
        return $this->exportService->exportClients($request->get('status'));
    }

    public function bookings(Request $request)
    {
        return $this->exportService->exportBookings($request->get('status'));
    }

    public function subscriptions(Request $request)
    {
        return $this->exportService->exportSubscriptions($request->get('status'));
    }

    public function lockers()
    {
        return $this->exportService->exportLockers();
    }

    public function all()
    {
        return $this->exportService->exportAllData();
    }

    public function report(Request $request)
    {
        $period = $request->get('period', 'month');
        $now = Carbon::now();

        $dateRange = match ($period) {
            'today' => ['start' => $now->copy()->startOfDay(), 'end' => $now->copy()->endOfDay()],
            'week' => ['start' => $now->copy()->startOfWeek(), 'end' => $now->copy()->endOfWeek()],
            'month' => ['start' => $now->copy()->startOfMonth(), 'end' => $now->copy()->endOfMonth()],
            'quarter' => ['start' => $now->copy()->startOfQuarter(), 'end' => $now->copy()->endOfQuarter()],
            'year' => ['start' => $now->copy()->startOfYear(), 'end' => $now->copy()->endOfYear()],
            'all' => ['start' => Carbon::create(2020, 1, 1), 'end' => $now->copy()->endOfDay()],
            default => ['start' => $now->copy()->startOfMonth(), 'end' => $now->copy()->endOfMonth()],
        };

        return $this->exportService->exportDashboardReport($dateRange['start'], $dateRange['end']);
    }
}
