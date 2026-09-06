<?php

namespace App\Queries\Cinema;

use App\Models\Cinema\Booking;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class UserBookingsQuery
{
    public function paginate(User $user, int $perPage = 10): LengthAwarePaginator
    {
        return Booking::query()
            ->select(['id', 'user_id', 'screening_id', 'status', 'expires_at', 'total_minor_units', 'pricing_currency', 'created_at'])
            ->with(['screening.movie:id,title,slug', 'screening.room:id,name,code,timezone', 'items.screeningSeat.seat'])
            ->where('user_id', $user->id)
            ->latest()->paginate($perPage)->withQueryString();
    }
}
