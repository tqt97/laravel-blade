<?php

namespace App\Http\Controllers\Cinema;

use App\Actions\Booking\HoldSeats;
use App\Enums\Cinema\ScreeningSeatStatus;
use App\Enums\Cinema\ScreeningStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\HoldSeatsRequest;
use App\Models\Cinema\Movie;
use App\Models\Cinema\Screening;
use App\Models\Cinema\ScreeningSeat;
use App\Models\User;
use App\Queries\Cinema\ScreeningBookingContextQuery;
use App\Support\Booking\SeatHoldConflict;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class PublicCinemaController extends Controller
{
    public function index(Request $request): View
    {
        $movies = Movie::query()
            ->where('is_active', true)
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->trim().'%';
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', $search)->orWhere('synopsis', 'like', $search);
                });
            })
            ->whereHas('screenings', fn ($query) => $query->where('status', ScreeningStatus::Scheduled)->where('starts_at', '>', now()->utc()))
            ->with(['screenings' => fn ($query) => $query->where('status', ScreeningStatus::Scheduled)->where('starts_at', '>', now()->utc())->orderBy('starts_at')->limit(3)])
            ->orderByDesc('release_date')->paginate(12);

        return view('cinema.movies.index', compact('movies'));
    }

    public function movie(Movie $movie): View
    {
        abort_unless($movie->is_active, 404);
        $availabilityCutoff = now()->utc();
        $movie->load(['screenings' => fn ($query) => $query
            ->where('status', ScreeningStatus::Scheduled)
            ->where('starts_at', '>', $availabilityCutoff)
            ->with(['room:id,name,timezone'])
            ->withCount('screeningSeats')
            ->withCount(['screeningSeats as available_screening_seats_count' => fn ($seatQuery) => $seatQuery->where(function ($availabilityQuery) use ($availabilityCutoff): void {
                $availabilityQuery
                    ->where('status', ScreeningSeatStatus::Available)
                    ->orWhere(fn ($heldQuery) => $heldQuery
                        ->where('status', ScreeningSeatStatus::Held)
                        ->where('held_until', '<=', $availabilityCutoff));
            })])
            ->orderBy('starts_at')]);
        $screeningSummaries = [];
        foreach ($movie->screenings as $screening) {
            if ($screening instanceof Screening) {
                $screeningSummaries[$screening->id] = ['available' => (int) $screening->available_screening_seats_count, 'total' => (int) $screening->screening_seats_count];
            }
        }

        return view('cinema.movies.show', compact('movie', 'screeningSummaries'));
    }

    public function screening(Request $request, Screening $screening, ScreeningBookingContextQuery $bookingContext): View
    {
        $status = ScreeningStatus::tryFrom((string) $screening->getRawOriginal('status'));
        $startsAt = CarbonImmutable::parse((string) $screening->getRawOriginal('starts_at'), 'UTC');
        abort_unless($status === ScreeningStatus::Scheduled && $startsAt->isFuture(), 404);
        $screening->load(['movie', 'room', 'screeningSeats.seat']);
        $seatSummary = $this->seatSummary($screening);
        $activeHold = null;
        $activeHoldSeatIds = [];
        if ($request->user() !== null) {
            /** @var User $user */
            $user = $request->user();
            $activeHold = $bookingContext->activeHold($user, $screening);
            $activeHoldSeatIds = $bookingContext->seatIds($activeHold);
        }

        return view('cinema.screenings.show', compact('screening', 'seatSummary', 'activeHold', 'activeHoldSeatIds'));
    }

    public function hold(HoldSeatsRequest $request, Screening $screening, HoldSeats $holdSeats): RedirectResponse
    {
        if ($request->user() === null) {
            $request->session()->put('cinema.pending_hold', [
                'screening_id' => $screening->id,
                'seat_ids' => array_values($request->validated('seat_ids')),
                'idempotency_key' => $request->validated('idempotency_key'),
            ]);
            $request->session()->put('url.intended', route('user.cinema.hold.resume'));

            return redirect()->guest(route('login'));
        }

        /** @var User $user */
        $user = $request->user();

        return $this->createHold($user, $screening, $request->validated('seat_ids'), $request->validated('idempotency_key'), $holdSeats);
    }

    public function availability(Screening $screening): JsonResponse
    {
        $startsAt = $screening->getRawOriginal('starts_at');
        abort_unless($screening->getRawOriginal('status') === ScreeningStatus::Scheduled->value && $startsAt !== null && CarbonImmutable::parse((string) $startsAt, 'UTC')->isFuture(), 404);

        return response()->json([
            'seats' => $screening->screeningSeats()->get(['seat_id', 'status'])->mapWithKeys(fn (ScreeningSeat $seat): array => [(string) $seat->seat_id => $seat->isAvailableForSelection()])->all(),
            'updated_at' => now()->utc()->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    }

    public function resumeHold(Request $request, HoldSeats $holdSeats): RedirectResponse
    {
        $pending = $request->session()->pull('cinema.pending_hold');
        if (! is_array($pending) || ! isset($pending['screening_id'], $pending['seat_ids'], $pending['idempotency_key']) || $request->user() === null) {
            return to_route('cinema.movies.index');
        }

        $screening = Screening::query()->findOrFail((int) $pending['screening_id']);
        /** @var User $user */
        $user = $request->user();

        try {
            $booking = $holdSeats->execute($user, $screening, (array) $pending['seat_ids'], (string) $pending['idempotency_key']);
        } catch (SeatHoldConflict $exception) {
            throw ValidationException::withMessages(['seat_ids' => $exception->getMessage()]);
        }

        return to_route('user.screenings.show', $screening)->with('booking_resume', true);
    }

    /** @param list<int|string> $seatIds */
    private function createHold(User $user, Screening $screening, array $seatIds, string $idempotencyKey, HoldSeats $holdSeats): RedirectResponse
    {
        try {
            $booking = $holdSeats->execute($user, $screening, $seatIds, $idempotencyKey);
        } catch (SeatHoldConflict $exception) {
            throw ValidationException::withMessages(['seat_ids' => $exception->getMessage()]);
        }

        return to_route('user.bookings.checkout', $booking);
    }

    /** @return array{available:int, total:int} */
    private function seatSummary(Screening $screening): array
    {
        $seats = $screening->screeningSeats;

        return ['available' => $seats->filter(fn ($seat): bool => $seat instanceof ScreeningSeat && $seat->isAvailableForSelection())->count(), 'total' => $seats->count()];
    }
}
