<?php

namespace App\Actions\Movie\Catalog;

use App\Models\Movie\Movie;

final class CreateMovie
{
    /** @param array<string, mixed> $attributes */
    public function execute(array $attributes): Movie
    {
        return Movie::query()->create($attributes);
    }
}
