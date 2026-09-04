<?php

namespace App\Booking\Queries;

use App\Booking\Models\Booking;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class UserBookingsQuery
{
    public function paginate(User $user, int $perPage = 10): LengthAwarePaginator
    {
        return Booking::query()
            ->select(['id', 'resource_id', 'status', 'start_at', 'end_at', 'expires_at', 'created_at'])
            ->with('resource:id,name,slug,timezone')
            ->where('user_id', $user->id)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }
}
