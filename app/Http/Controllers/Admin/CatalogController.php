<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Catalog\CreateMovie;
use App\Actions\Catalog\CreateScreening;
use App\Actions\Catalog\CreateScreeningRoom;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMovieRequest;
use App\Http\Requests\Admin\StoreScreeningRequest;
use App\Http\Requests\Admin\StoreScreeningRoomRequest;
use App\Models\Catalog\Movie;
use App\Models\Catalog\Screening;
use App\Models\Catalog\ScreeningRoom;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CatalogController extends Controller
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
