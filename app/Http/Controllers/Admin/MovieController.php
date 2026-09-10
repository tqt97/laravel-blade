<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Movie\Catalog\CreateCoupon;
use App\Actions\Movie\Catalog\CreateMovie;
use App\Actions\Movie\Catalog\CreateScreening;
use App\Actions\Movie\Catalog\CreateScreeningRoom;
use App\Actions\Movie\Concessions\CreateConcession;
use App\Actions\Movie\Concessions\UpdateConcession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveConcessionRequest;
use App\Http\Requests\Admin\SaveCouponRequest;
use App\Http\Requests\Admin\StoreMovieRequest;
use App\Http\Requests\Admin\StoreScreeningRequest;
use App\Http\Requests\Admin\StoreScreeningRoomRequest;
use App\Models\Movie\Concession;
use App\Models\Movie\Coupon;
use App\Models\Movie\Movie;
use App\Models\Movie\Screening;
use App\Models\Movie\ScreeningRoom;
use App\Support\Booking\Exceptions\BookingOperationFailed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MovieController extends Controller
{
    public function index(): View
    {
        return view('admin.cinema.index', [
            'movies' => Movie::query()->select([
                'id',
                'title',
            ])
                ->latest()
                ->paginate((int) config('booking.listing.admin_page_size'), pageName: 'movies_page'),
            'rooms' => ScreeningRoom::query()->select([
                'id',
                'name',
            ])
                ->withCount('seats')
                ->latest()
                ->paginate((int) config('booking.listing.admin_page_size'), pageName: 'rooms_page'),
            'screenings' => Screening::query()->with(['movie:id,title', 'room:id,name'])->latest('starts_at')->paginate((int) config('booking.listing.admin_page_size')),
        ]);
    }

    public function concessions(): View
    {
        return view('admin.cinema.concessions', [
            'concessions' => Concession::query()->latest()->paginate((int) config('booking.listing.admin_page_size')),
        ]);
    }

    public function coupons(): View
    {
        return view('admin.cinema.coupons', [
            'coupons' => Coupon::query()->latest()->paginate((int) config('booking.listing.admin_page_size')),
        ]);
    }

    public function storeCoupon(SaveCouponRequest $request, CreateCoupon $createCoupon): RedirectResponse
    {
        $createCoupon->execute($request->validated());

        return back()->with('status', 'cinema.admin.coupon_created');
    }

    public function storeConcession(SaveConcessionRequest $request, CreateConcession $createConcession): RedirectResponse
    {
        $createConcession->execute($request->validated(), $request->user());

        return back()->with('status', 'cinema.admin.concession_created');
    }

    public function updateConcession(SaveConcessionRequest $request, Concession $concession, UpdateConcession $updateConcession): RedirectResponse
    {
        try {
            $updateConcession->execute($concession, $request->validated(), $request->user());
        } catch (BookingOperationFailed $exception) {
            throw ValidationException::withMessages(['stock_reason' => $exception->getMessage()]);
        }

        return back()->with('status', 'cinema.admin.concession_updated');
    }

    public function storeMovie(StoreMovieRequest $request, CreateMovie $createMovie): RedirectResponse
    {
        $createMovie->execute($request->validated());

        return back()->with('status', 'cinema.admin.movie_created');
    }

    public function storeRoom(StoreScreeningRoomRequest $request, CreateScreeningRoom $createScreeningRoom): RedirectResponse
    {
        $createScreeningRoom->execute($request->validated());

        return back()->with('status', 'cinema.admin.room_created');
    }

    public function storeScreening(StoreScreeningRequest $request, CreateScreening $createScreening): RedirectResponse
    {
        $data = $request->validated();

        $createScreening->execute(
            Movie::query()->findOrFail($data['movie_id']),
            ScreeningRoom::query()->findOrFail($data['screening_room_id']),
            $data['starts_at'],
            $data['ends_at'],
            $data['base_price_minor_units'],
            strtoupper($data['currency'])
        );

        return back()->with('status', 'cinema.admin.screening_created');
    }
}
