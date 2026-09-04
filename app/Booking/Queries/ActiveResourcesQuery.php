<?php

namespace App\Booking\Queries;

use App\Models\BookableResource;
use Illuminate\Database\Eloquent\Collection;

final class ActiveResourcesQuery
{
    public function get(): Collection
    {
        return BookableResource::query()
            ->select(['id', 'name', 'slug', 'description', 'timezone'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
