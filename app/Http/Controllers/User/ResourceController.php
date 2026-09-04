<?php

namespace App\Http\Controllers\User;

use App\Booking\Queries\ActiveResourcesQuery;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ResourceController extends Controller
{
    public function index(ActiveResourcesQuery $query): View
    {
        return view('user.resources.index', ['resources' => $query->get()]);
    }
}
