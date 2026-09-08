<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Cinema\CreateScreening;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveConcessionRequest;
use App\Http\Requests\Admin\StoreMovieRequest;
use App\Http\Requests\Admin\StoreScreeningRequest;
use App\Http\Requests\Admin\StoreScreeningRoomRequest;
use App\Models\Cinema\Concession;
use App\Models\Cinema\ConcessionInventoryMovement;
use App\Models\Cinema\ConcessionStockAdjustmentAudit;
use App\Models\Cinema\Movie;
use App\Models\Cinema\Screening;
use App\Models\Cinema\ScreeningRoom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CinemaController extends Controller
{
    public function index(): View
    {
        return view('admin.cinema.index', [
            'movies' => Movie::query()->select(['id', 'title'])->latest()->paginate(20, pageName: 'movies_page'),
            'rooms' => ScreeningRoom::query()->select(['id', 'name'])->withCount('seats')->latest()->paginate(20, pageName: 'rooms_page'),
            'screenings' => Screening::query()->with(['movie:id,title', 'room:id,name'])->latest('starts_at')->paginate(15),
        ]);
    }

    public function concessions(): View
    {
        return view('admin.cinema.concessions', [
            'concessions' => Concession::query()->latest()->paginate(20),
        ]);
    }

    public function storeConcession(SaveConcessionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($data, $request): void {
            $concession = Concession::query()->create($this->concessionAttributes($data));
            if ($concession->stock !== null) {
                ConcessionInventoryMovement::query()->create(['concession_id' => $concession->getKey(), 'actor_id' => $request->user()->id, 'type' => 'initial', 'quantity_delta' => (int) $concession->stock, 'stock_before' => null, 'stock_after' => (int) $concession->stock, 'reference' => 'concession-'.$concession->getKey()]);
            }
        }, 3);

        return back()->with('status', 'cinema.admin.concession_created');
    }

    public function updateConcession(SaveConcessionRequest $request, Concession $concession): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($concession, $data): void {
            $locked = Concession::query()->whereKey($concession->getKey())->lockForUpdate()->firstOrFail();
            $stockBefore = $locked->stock;
            $newStock = array_key_exists('stock', $data) ? ($data['stock'] === null ? null : (int) $data['stock']) : $stockBefore;
            $stockDelta = ($newStock ?? 0) - ($stockBefore ?? 0);
            if ($stockDelta !== 0 && blank($data['stock_reason'] ?? null)) {
                throw ValidationException::withMessages(['stock_reason' => __('cinema.admin.concession_stock_reason_required')]);
            }
            $locked->update($this->concessionAttributes($data));
            if ($stockDelta !== 0) {
                ConcessionStockAdjustmentAudit::query()->create(['concession_id' => $locked->getKey(), 'actor_id' => request()->user()->id, 'quantity_delta' => $stockDelta, 'stock_before' => $stockBefore, 'stock_after' => $newStock, 'reason' => $data['stock_reason']]);
                ConcessionInventoryMovement::query()->create(['concession_id' => $locked->getKey(), 'actor_id' => request()->user()->id, 'type' => 'adjustment', 'quantity_delta' => $stockDelta, 'stock_before' => $stockBefore, 'stock_after' => $newStock, 'reference' => 'admin-adjustment-'.$locked->getKey(), 'metadata' => ['reason' => $data['stock_reason']]]);
            }
        }, 3);

        return back()->with('status', 'cinema.admin.concession_updated');
    }

    /** @param array<string, mixed> $validated */
    private function concessionAttributes(array $validated): array
    {
        unset($validated['stock_reason']);
        $validated['currency'] = strtoupper((string) $validated['currency']);
        $validated['image_url'] = filled($validated['image_url'] ?? null) ? $validated['image_url'] : null;
        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        return $validated;
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
