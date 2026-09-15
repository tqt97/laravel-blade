<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Queries\Booking\BookingReport;
use App\Support\Booking\BookingClock;
use Illuminate\View\View;

final class BookingReportController extends Controller
{
    public function __invoke(BookingReport $report): View
    {
        $now = BookingClock::now();
        $summary = $report->summary($now->startOfMonth(), $now->endOfMonth());

        return view('admin.reports.index', compact('summary'));
    }
}
