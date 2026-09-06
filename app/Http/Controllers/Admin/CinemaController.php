<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Cinema\CreateScreening;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMovieRequest;
use App\Http\Requests\Admin\StoreScreeningRequest;
use App\Http\Requests\Admin\StoreScreeningRoomRequest;
use App\Models\Cinema\Movie;
use App\Models\Cinema\Screening;
use App\Models\Cinema\ScreeningRoom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CinemaController extends Controller
{
    public function index(): View
    {
        return view('admin.cinema.index', ['movies' => Movie::query()->latest()->get(), 'rooms' => ScreeningRoom::query()->withCount('seats')->latest()->get(), 'screenings' => Screening::query()->with(['movie:id,title', 'room:id,name'])->latest('starts_at')->paginate(15)]);
    }

    public function storeMovie(StoreMovieRequest $request): RedirectResponse
    {
        Movie::query()->create($request->validated());

        return back()->with('status', 'cinema.admin.movie_created');
    }

    public function storeRoom(StoreScreeningRoomRequest $request): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($data): void {
            $room = ScreeningRoom::query()->create(['name' => $data['name'], 'code' => $data['code'], 'timezone' => $data['timezone'], 'is_active' => true]);
            foreach (array_map('trim', explode(',', $data['rows'])) as $row) {
                for ($number = 1; $number <= $data['seats_per_row']; $number++) {
                    $room->seats()->create(['row_label' => $row, 'seat_number' => $number, 'seat_type' => 'regular', 'price_minor_units' => 0, 'is_active' => true]);
                }
            }
        }, 3);

        return back()->with('status', 'cinema.admin.room_created');
    }

    public function storeScreening(StoreScreeningRequest $request, CreateScreening $createScreening): RedirectResponse
    {
        $data = $request->validated();
        $createScreening->execute(Movie::query()->findOrFail($data['movie_id']), ScreeningRoom::query()->findOrFail($data['screening_room_id']), $data['starts_at'], $data['ends_at'], $data['base_price_minor_units'], strtoupper($data['currency']));

        return back()->with('status', 'cinema.admin.screening_created');
    }
}
