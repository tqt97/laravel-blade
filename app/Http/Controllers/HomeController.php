<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

final class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('welcome');
    }
}
