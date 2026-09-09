<?php

namespace App\Queries\Movie;

use App\Enums\Movie\Booking\BookingStatus;
use App\Models\Movie\Booking;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class UserBookingsQuery
{
    public function paginate(User $user, ?string $status = null, int $perPage = 10): LengthAwarePaginator
    {
        $query = Booking::query()
            ->select(['id', 'user_id', 'screening_id', 'status', 'expires_at', 'total_minor_units', 'pricing_currency', 'created_at'])
            ->with(['screening.movie:id,title,slug', 'screening.room:id,name,code,timezone'])
            ->withCount(['items', 'concessions'])
            ->where('user_id', $user->id);

        $statusEnum = $status !== null ? BookingStatus::tryFrom($status) : null;
        if ($statusEnum !== null) {
            $query->where('status', $statusEnum);
        }

        return $query->latest()->paginate($perPage)->withQueryString();
    }
}
