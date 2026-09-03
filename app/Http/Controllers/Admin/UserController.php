<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\CreateUser;
use App\Actions\Admin\DeleteUser;
use App\Actions\Admin\DeleteUsers;
use App\Actions\Admin\ForceDeleteUser;
use App\Actions\Admin\ForceDeleteUsers;
use App\Actions\Admin\RestoreUser;
use App\Actions\Admin\RestoreUsers;
use App\Actions\Admin\UpdateUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkDeleteUsersRequest;
use App\Http\Requests\Admin\BulkForceDeleteUsersRequest;
use App\Http\Requests\Admin\BulkRestoreUsersRequest;
use App\Http\Requests\Admin\IndexUserRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Queries\Admin\UserIndexQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(IndexUserRequest $request, UserIndexQuery $query): View
    {
        $users = $query->paginate($request->validated());

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.create');
    }

    public function store(StoreUserRequest $request, CreateUser $createUser): RedirectResponse
    {
        $createUser->execute($request->validated(), $request->user());

        return to_route('admin.users.index')->with('status', 'admin.users.messages.created');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('admin.users.edit', compact('user'));
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUser $updateUser): RedirectResponse
    {
        $updateUser->execute($user, $request->validated(), $request->user());

        return to_route('admin.users.index')->with('status', 'admin.users.messages.updated');
    }

    public function destroy(Request $request, User $user, DeleteUser $deleteUser): RedirectResponse
    {
        $this->authorize('delete', $user);
        $deleteUser->execute($user, $request->user());

        return to_route('admin.users.index')->with('status', 'admin.users.messages.deleted');
    }

    public function bulkDestroy(BulkDeleteUsersRequest $request, DeleteUsers $deleteUsers): RedirectResponse
    {
        $deletedCount = $deleteUsers->execute($request->user(), $request->validated('ids'));

        return to_route('admin.users.index')->with('status', [
            'key' => 'admin.users.messages.bulk_deleted',
            'replace' => ['count' => $deletedCount],
        ]);
    }

    public function restore(Request $request, int $userId, RestoreUser $restoreUser): RedirectResponse
    {
        $user = User::onlyTrashed()->findOrFail($userId);
        $this->authorize('restore', $user);
        $restoreUser->execute($user, $request->user());

        return to_route('admin.users.index', ['status' => 'deleted'])->with('status', 'admin.users.messages.restored');
    }

    public function bulkRestore(BulkRestoreUsersRequest $request, RestoreUsers $restoreUsers): RedirectResponse
    {
        $restoredCount = $restoreUsers->execute($request->user(), $request->validated('ids'));

        return to_route('admin.users.index', ['status' => 'deleted'])->with('status', [
            'key' => 'admin.users.messages.bulk_restored',
            'replace' => ['count' => $restoredCount],
        ]);
    }

    public function forceDestroy(Request $request, int $userId, ForceDeleteUser $forceDeleteUser): RedirectResponse
    {
        $user = User::onlyTrashed()->findOrFail($userId);
        $this->authorize('forceDelete', $user);
        $forceDeleteUser->execute($user, $request->user());

        return to_route('admin.users.index', ['status' => 'deleted'])->with('status', 'admin.users.messages.force_deleted');
    }

    public function bulkForceDestroy(BulkForceDeleteUsersRequest $request, ForceDeleteUsers $forceDeleteUsers): RedirectResponse
    {
        $deletedCount = $forceDeleteUsers->execute($request->user(), $request->validated('ids'));

        return to_route('admin.users.index', ['status' => 'deleted'])->with('status', [
            'key' => 'admin.users.messages.bulk_force_deleted',
            'replace' => ['count' => $deletedCount],
        ]);
    }
}
