<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

final class RedirectToMovieCatalogueController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return to_route('cinema.movies.index');
    }
}
