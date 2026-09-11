<?php

namespace App\Http\Controllers\Movie;

use App\Actions\Movie\Booking\EditBookingSelection;
use App\Enums\Movie\Booking\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\HoldSeatsRequest;
use App\Models\Movie\Movie;
use App\Models\Movie\Screening;
use App\Models\Movie\ScreeningSeat;
use App\Models\User;
use App\Queries\Movie\AvailableConcessionsQuery;
use App\Queries\Movie\ScreeningBookingContextQuery;
use App\Support\Booking\Exceptions\BookingOperationFailed;
use App\Support\Booking\SeatHoldConflict;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class PublicMovieController extends Controller
{
    public function index(Request $request): View
    {
        $movies = Movie::query()
            ->active()
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->trim().'%';
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', $search)->orWhere('synopsis', 'like', $search);
                });
            })
            ->hasBookableScreenings()
            ->with(['screenings' => fn ($query) => $query->bookable()
                ->orderBy('starts_at')
                ->limit((int) config('booking.listing.screening_preview_limit'))])
            ->orderByDesc('release_date')
            ->paginate((int) config('booking.listing.movies_per_page'));

        return view('cinema.movies.index', compact('movies'));
    }

    public function sitemap(): Response
    {
        $movies = Movie::query()
            ->active()
            ->hasBookableScreenings()
            ->with(['screenings' => fn ($query) => $query->bookable()->orderBy('starts_at')])
            ->get();

        return response()->view('seo.sitemap', compact('movies'))->header('Content-Type', 'application/xml');
    }

    public function movie(Movie $movie): View
    {
        abort_unless($movie->is_active, 404);

        $movie->load(['screenings' => fn ($query) => $query
            ->bookable()
            ->with(['room:id,name,timezone'])
            ->withCount('screeningSeats')
            ->withCount(['screeningSeats as available_screening_seats_count' => fn ($seatQuery) => $seatQuery->where(function ($availabilityQuery): void {
                $availabilityQuery->availableForSelection();
            })])
            ->orderBy('starts_at')]);
        $screeningSummaries = [];

        foreach ($movie->screenings as $screening) {
            $screeningSummaries[$screening->id] = [
                'available' => (int) $screening->getAttribute('available_screening_seats_count'),
                'total' => (int) $screening->getAttribute('screening_seats_count'),
            ];
        }

        return view('cinema.movies.show', compact('movie', 'screeningSummaries'));
    }

    public function screening(Request $request, Movie $movie, Screening $screening, ScreeningBookingContextQuery $bookingContext, AvailableConcessionsQuery $concessionsQuery): View|RedirectResponse
    {
        abort_unless($screening->movie_id === $movie->id, 404);
        abort_unless($screening->isBookable(), 404);

        $screening->load(['movie', 'room', 'screeningSeats.seat']);
        $seatSummary = $this->seatSummary($screening);
        $activeHold = null;
        $activeHoldSeatIds = [];

        if ($request->user() !== null) {
            /** @var User $user */
            $user = $request->user();
            $activeHold = $bookingContext->activeHold($user, $screening);

            if ($activeHold?->getRawOriginal('status') === BookingStatus::PendingPayment->value) {
                return to_route('user.bookings.checkout', $activeHold);
            }
            $activeHold?->load(['concessions', 'items.screeningSeat.seat']);
            $activeHoldSeatIds = $activeHold?->getRawOriginal('status') === BookingStatus::Held->value
                ? $bookingContext->seatIds($activeHold)
                : [];
        }

        $concessions = $concessionsQuery->get((string) $screening->currency);

        return view('cinema.screenings.show', compact('screening', 'seatSummary', 'activeHold', 'activeHoldSeatIds', 'concessions'));
    }

    public function hold(HoldSeatsRequest $request, Movie $movie, Screening $screening, EditBookingSelection $editBookingSelection): RedirectResponse
    {
        abort_unless($screening->movie_id === $movie->id, 404);

        if ($request->user() === null) {
            $request->session()->put('cinema.pending_hold', [
                'screening_id' => $screening->id,
                'seat_ids' => array_values($request->validated('seat_ids')),
                'idempotency_key' => $request->validated('idempotency_key'),
                'quantities' => $request->validated('quantities', []),
            ]);
            $request->session()->put('url.intended', route('user.cinema.hold.resume'));

            return redirect()->route('login');
        }

        /** @var User $user */
        $user = $request->user();

        try {
            $booking = $editBookingSelection->execute($user, $screening, $request->validated('seat_ids'), $request->validated('idempotency_key'), $request->validated('quantities', []));
        } catch (SeatHoldConflict $exception) {
            throw ValidationException::withMessages(['seat_ids' => $exception->getMessage()]);
        } catch (BookingOperationFailed $exception) {
            throw ValidationException::withMessages(['quantities' => $exception->getMessage()]);
        }

        return to_route('user.bookings.checkout', $booking);
    }

    public function availability(Request $request, Movie $movie, Screening $screening, ScreeningBookingContextQuery $bookingContext): JsonResponse
    {
        abort_unless($screening->movie_id === $movie->id, 404);
        abort_unless($screening->isBookable(), 404);

        $ownedSeatIds = [];
        if ($request->user() !== null) {
            /** @var User $user */
            $user = $request->user();
            $ownedSeatIds = array_map('strval', $bookingContext->ownedSeatIds($user, $screening));
        }

        $seats = ScreeningSeat::query()
            ->where('screening_id', $screening->getKey())
            ->get(['seat_id', 'status', 'held_until'])
            ->mapWithKeys(function (ScreeningSeat $seat) use ($ownedSeatIds): array {
                return [(string) $seat->seat_id => [
                    'available' => $seat->isAvailableForSelection(),
                    'owned_by_current_booking' => in_array((string) $seat->seat_id, $ownedSeatIds, true),
                ]];
            })->all();
        $serverNow = now();

        return response()->json([
            'seats' => $seats,
            'availability_version' => hash('sha256', json_encode($seats, JSON_THROW_ON_ERROR)),
            'updated_at' => $serverNow->toIso8601String(),
            'server_now' => $serverNow->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    }

    public function resumeHold(Request $request, EditBookingSelection $editBookingSelection): RedirectResponse
    {
        $pending = $request->session()->get('cinema.pending_hold');

        if (! is_array($pending) || ! isset($pending['screening_id'], $pending['seat_ids'], $pending['idempotency_key']) || $request->user() === null) {
            return to_route('cinema.movies.index');
        }

        $screening = Screening::query()->find((int) $pending['screening_id']);
        if ($screening === null) {
            return to_route('cinema.movies.index')->withErrors(['booking' => __('booking.messages.screening_unavailable')]);
        }
        $screening->load('movie');
        /** @var User $user */
        $user = $request->user();

        try {
            $booking = $editBookingSelection->execute($user, $screening, (array) $pending['seat_ids'], (string) $pending['idempotency_key'], (array) ($pending['quantities'] ?? []));
        } catch (SeatHoldConflict $exception) {
            $request->session()->put('cinema.pending_hold', $pending);

            return to_route('cinema.screenings.show', [$screening->movie, $screening])
                ->withErrors(['seat_ids' => $exception->getMessage()]);
        } catch (BookingOperationFailed $exception) {
            $request->session()->put('cinema.pending_hold', $pending);

            return to_route('cinema.screenings.show', [$screening->movie, $screening])
                ->withErrors(['quantities' => $exception->getMessage()]);
        }

        $request->session()->forget('cinema.pending_hold');

        return to_route('cinema.screenings.show', [$screening->movie, $screening])->with('booking_resume', true);
    }

    /** @return array{available:int, total:int} */
    private function seatSummary(Screening $screening): array
    {
        $seats = $screening->screeningSeats;

        return ['available' => $seats->filter(fn ($seat): bool => $seat instanceof ScreeningSeat && $seat->isAvailableForSelection())->count(), 'total' => $seats->count()];
    }
}
