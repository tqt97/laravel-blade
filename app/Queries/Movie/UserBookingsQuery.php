<?php

namespace App\Queries\Movie;

use App\Enums\Movie\Booking\BookingStatus;
use App\Models\Movie\Booking;
use App\Models\Movie\Screening;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final class UserBookingsQuery
{
    public function paginate(User $user, ?string $status = null, ?int $perPage = null): LengthAwarePaginator
    {
        $perPage ??= (int) config('booking.listing.user_bookings_per_page');
        $query = Booking::query()
            ->select([
                'id',
                'user_id',
                'screening_id',
                'status',
                'expires_at',
                'total_minor_units',
                'pricing_currency',
                'created_at',
            ])
            ->with(['screening.movie:id,title,slug', 'screening.room:id,name,code,timezone'])
            ->withCount(['items', 'concessions'])
            ->ownedBy($user->id);

        $statusEnum = $status !== null ? BookingStatus::tryFrom($status) : null;
        if ($statusEnum !== null) {
            $query->where('status', $statusEnum);
        }

        return $query->latest()->paginate($perPage)->withQueryString();
    }

    public function upcoming(User $user): ?Booking
    {
        return Booking::query()
            ->ownedBy($user->id)
            ->select([
                'bookings.id',
                'bookings.user_id',
                'bookings.screening_id',
                'bookings.status',
                'bookings.expires_at',
                'bookings.total_minor_units',
                'bookings.pricing_currency',
            ])
            ->with([
                'screening:id,movie_id,screening_room_id,starts_at,ends_at',
                'screening.movie:id,title',
                'screening.room:id,name,timezone',
            ])
            ->withCount(['items', 'concessions'])
            ->upcoming()
            ->whereIn('screening_id', Screening::query()->startsAfter()->select('id'))
            ->join('screenings', 'bookings.screening_id', '=', 'screenings.id')
            ->orderBy('screenings.starts_at')
            ->select('bookings.*')
            ->first();
    }

    /** @return Collection<int, Booking> */
    public function recent(User $user, ?int $limit = null): Collection
    {
        $limit ??= (int) config('booking.listing.dashboard_recent_bookings');

        return Booking::query()
            ->ownedBy($user->id)
            ->select([
                'bookings.id',
                'bookings.user_id',
                'bookings.screening_id',
                'bookings.status',
                'bookings.expires_at',
                'bookings.total_minor_units',
                'bookings.pricing_currency',
                'bookings.created_at',
            ])
            ->with([
                'screening:id,movie_id,screening_room_id,starts_at,ends_at',
                'screening.movie:id,title',
                'screening.room:id,name,timezone',
            ])
            ->withCount(['items', 'concessions'])
            ->latest()
            ->limit($limit)
            ->get();
    }
}
