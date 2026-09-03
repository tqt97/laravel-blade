<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects regular users to the user dashboard after login', function (): void {
    $user = User::factory()->create(['email' => 'member@example.com']);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('user.dashboard'));
});

it('redirects administrators to the admin dashboard after login', function (): void {
    $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);

    $this->post('/login', [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard'));
});

it('redirects guests away from user management', function (): void {
    $this->get(route('admin.users.index'))
        ->assertRedirect(route('login'));
});

it('forbids non administrators from user management', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

it('forbids non administrators from every admin route', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

it('lists users with search filters and pagination', function (): void {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['name' => 'Searchable User', 'email' => 'searchable@example.com']);
    User::factory()->create(['name' => 'Other User', 'email' => 'other@example.com']);

    $response = $this->actingAs($admin)->get(route('admin.users.index', [
        'search' => 'searchable',
        'per_page' => 15,
    ]));

    $response->assertOk()
        ->assertViewHas('users', fn ($users): bool => $users->total() === 1);
});

it('paginates the user list with the requested page size', function (): void {
    $admin = User::factory()->admin()->create();
    User::factory()->count(16)->create();

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['per_page' => 15]))
        ->assertViewHas('users', fn ($users): bool => $users->perPage() === 15 && $users->total() === 17);
});

it('uses lifecycle, role, verification and two factor filters together', function (): void {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['name' => 'Active admin'])->forceFill([
        'is_admin' => true,
        'email_verified_at' => now(),
        'two_factor_confirmed_at' => now(),
    ])->save();
    $matching = User::factory()->create([
        'name' => 'Matching user',
        'email_verified_at' => now(),
    ]);
    $matching->forceFill(['two_factor_confirmed_at' => now()])->save();
    $deleted = User::factory()->create(['name' => 'Deleted user']);
    $deleted->delete();

    $this->actingAs($admin)
        ->get(route('admin.users.index', [
            'role' => 'user',
            'verification' => 'verified',
            'two_factor' => 'enabled',
            'status' => 'active',
        ]))
        ->assertViewHas('users', fn ($users): bool => $users->total() === 1 && $users->first()->is($matching));
});

it('validates required and unique user fields', function (): void {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['email' => 'existing@example.com']);

    $this->actingAs($admin)
        ->from(route('admin.users.create'))
        ->post(route('admin.users.store'), ['email' => 'existing@example.com'])
        ->assertRedirect(route('admin.users.create'))
        ->assertSessionHasErrors(['name', 'password', 'email']);
});

it('creates a user through the admin form', function (): void {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'New User',
        'email' => 'new@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'is_admin' => '0',
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'is_admin' => false]);
});

it('updates a user and preserves the password when omitted', function (): void {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['name' => 'Before', 'email' => 'before@example.com']);
    $oldPassword = $user->password;

    $response = $this->actingAs($admin)->put(route('admin.users.update', $user), [
        'name' => 'After',
        'email' => 'after@example.com',
        'is_admin' => '0',
    ]);

    $response->assertRedirect(route('admin.users.index'));
    expect($user->refresh()->name)->toBe('After')
        ->and($user->password)->toBe($oldPassword);
});

it('does not allow an administrator to delete themselves', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $admin))
        ->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});

it('deletes another user', function (): void {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $user))
        ->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

it('deletes multiple selected users and keeps unselected users', function (): void {
    $admin = User::factory()->admin()->create();
    $selectedUsers = User::factory()->count(2)->create();
    $unselectedUser = User::factory()->create();

    $response = $this->actingAs($admin)->delete(route('admin.users.bulk-destroy'), [
        'ids' => $selectedUsers->modelKeys(),
    ]);

    $response->assertRedirect(route('admin.users.index'));

    foreach ($selectedUsers as $user) {
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    $this->assertDatabaseHas('users', ['id' => $unselectedUser->id]);
});

it('does not allow bulk deletion of the current administrator', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->delete(route('admin.users.bulk-destroy'), ['ids' => [$admin->id]])
        ->assertSessionHasErrors('ids');

    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});

it('soft deletes, restores and force deletes a user', function (): void {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)->delete(route('admin.users.destroy', $user))->assertRedirect();
    $this->assertSoftDeleted('users', ['id' => $user->id]);

    $this->actingAs($admin)->patch(route('admin.users.restore', $user->id))->assertRedirect();
    $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);

    $this->actingAs($admin)->delete(route('admin.users.destroy', $user));
    $this->actingAs($admin)->delete(route('admin.users.force-delete', $user->id))->assertRedirect();
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

it('bulk restores and force deletes selected trashed users', function (): void {
    $admin = User::factory()->admin()->create();
    $users = User::factory()->count(3)->create();
    $users->each->delete();

    $this->actingAs($admin)->patch(route('admin.users.bulk-restore'), ['ids' => $users->take(2)->modelKeys()])->assertRedirect();
    $this->assertDatabaseHas('users', ['id' => $users[0]->id, 'deleted_at' => null]);
    $this->assertSoftDeleted('users', ['id' => $users[2]->id]);

    $this->actingAs($admin)->delete(route('admin.users.bulk-force-delete'), ['ids' => [$users[2]->id]])->assertRedirect();
    $this->assertDatabaseMissing('users', ['id' => $users[2]->id]);
});

it('rejects bulk actions when selected users are in the wrong lifecycle state', function (): void {
    $admin = User::factory()->admin()->create();
    $activeUser = User::factory()->create();
    $deletedUser = User::factory()->create();
    $deletedUser->delete();

    $this->actingAs($admin)
        ->patch(route('admin.users.bulk-restore'), ['ids' => [$activeUser->id]])
        ->assertSessionHasErrors('ids');

    $this->actingAs($admin)
        ->delete(route('admin.users.bulk-destroy'), ['ids' => [$deletedUser->id]])
        ->assertSessionHasErrors('ids');
});

it('does not serialize two factor secrets', function (): void {
    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => 'encrypted-secret',
        'two_factor_recovery_codes' => 'encrypted-codes',
    ])->save();

    expect($user->toArray())->not->toHaveKey('two_factor_secret')
        ->and($user->toArray())->not->toHaveKey('two_factor_recovery_codes');
});

it('writes an audit record for user lifecycle actions', function (): void {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $user))
        ->assertRedirect();

    $this->assertDatabaseHas('user_management_audits', [
        'actor_id' => $admin->id,
        'target_user_id' => $user->id,
        'action' => 'deleted',
    ]);
});
