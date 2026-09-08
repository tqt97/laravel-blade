<?php

namespace App\Queries\Cinema;

use App\Models\Cinema\Booking;
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

        if ($status !== null && in_array($status, ['held', 'pending_payment', 'confirmed', 'completed', 'cancelled', 'expired', 'no_show'], true)) {
            $query->where('status', $status);
        }

        return $query->latest()->paginate($perPage)->withQueryString();
    }
}
