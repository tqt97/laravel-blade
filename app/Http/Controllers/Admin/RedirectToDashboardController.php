<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

final class RedirectToDashboardController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return to_route('admin.dashboard');
    }
}
