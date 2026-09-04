<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreResourceRequest;
use App\Http\Requests\Admin\UpdateResourceRequest;
use App\Models\BookableResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ResourceController extends Controller
{
    public function index(): View
    {
        $resources = BookableResource::query()->latest()->paginate(20)->withQueryString();

        return view('admin.resources.index', compact('resources'));
    }

    public function create(): View
    {
        return view('admin.resources.create', ['resource' => new BookableResource(['timezone' => config('app.timezone', 'UTC')])]);
    }

    public function store(StoreResourceRequest $request): RedirectResponse
    {
        BookableResource::query()->create($request->validated());

        return to_route('admin.resources.index')->with('status', 'booking.admin.resource_created');
    }

    public function edit(BookableResource $resource): View
    {
        return view('admin.resources.edit', compact('resource'));
    }

    public function update(UpdateResourceRequest $request, BookableResource $resource): RedirectResponse
    {
        $resource->update($request->validated());

        return to_route('admin.resources.index')->with('status', 'booking.admin.resource_updated');
    }

    public function destroy(BookableResource $resource): RedirectResponse
    {
        $resource->delete();

        return to_route('admin.resources.index')->with('status', 'booking.admin.resource_deleted');
    }
}
