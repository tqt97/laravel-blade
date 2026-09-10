<?php

namespace App\Actions\Movie\Catalog;

use App\Models\Movie\Movie;

final class CreateMovie
{
    /** @param array<string, mixed> $attributes */
    public function execute(array $attributes): Movie
    {
        if (is_string($attributes['cast'] ?? null)) {
            $attributes['cast'] = array_values(array_filter(array_map('trim', explode(',', $attributes['cast']))));
        }

        return Movie::query()->create($attributes);
    }
}
