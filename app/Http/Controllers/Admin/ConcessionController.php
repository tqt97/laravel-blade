<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Commerce\Concessions\CreateConcession;
use App\Actions\Commerce\Concessions\UpdateConcession;
use App\Exceptions\Booking\BookingOperationFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveConcessionRequest;
use App\Models\Commerce\Concession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class ConcessionController extends Controller
{
    public function index(): View
    {
        return view('admin.cinema.concessions', [
            'concessions' => Concession::query()->latest()->paginate((int) config('booking.listing.admin_page_size')),
        ]);
    }

    public function store(SaveConcessionRequest $request, CreateConcession $createConcession): RedirectResponse
    {
        $createConcession->execute($request->validated(), $request->user());

        return back()->with('status', 'cinema.admin.concession_created');
    }

    public function update(SaveConcessionRequest $request, Concession $concession, UpdateConcession $updateConcession): RedirectResponse
    {
        try {
            $updateConcession->execute($concession, $request->validated(), $request->user());
        } catch (BookingOperationFailed $exception) {
            throw ValidationException::withMessages(['stock_reason' => $exception->getMessage()]);
        }

        return back()->with('status', 'cinema.admin.concession_updated');
    }
}
