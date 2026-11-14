<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function registerUserWithRole(string $role): User
{
    Role::findOrCreate($role);
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('blocks staff from the sales register by default', function () {
    $staff = registerUserWithRole('staff');

    $this->actingAs($staff)->get(route('reports.sales-register'))->assertForbidden();
    $this->actingAs($staff)->get(route('reports.sales-register.export'))->assertForbidden();
});

it('lets staff with the sales register permission open it', function () {
    $staff = registerUserWithRole('staff');
    $staff->givePermissionTo(Permission::findOrCreate(User::PERMISSION_VIEW_SALES_REGISTER, 'web'));

    $this->actingAs($staff)
        ->get(route('reports.sales-register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/SalesRegister')
            ->where('auth.permissions', [User::PERMISSION_VIEW_SALES_REGISTER])
        );
});

it('still lets managers open the sales register', function () {
    $this->actingAs(registerUserWithRole('manager'))
        ->get(route('reports.sales-register'))
        ->assertOk();
});

it('grants and revokes the permission from the user edit form', function () {
    $admin = registerUserWithRole('admin');
    $staff = registerUserWithRole('staff');
    $payload = fn (bool $allowed) => [
        'name' => $staff->name,
        'email' => $staff->email,
        'roles' => ['staff'],
        'status' => true,
        'can_view_sales_register' => $allowed,
    ];

    $this->actingAs($admin)->put(route('users.update', $staff), $payload(true))->assertRedirect(route('users.index'));
    expect($staff->fresh()->hasPermissionTo(User::PERMISSION_VIEW_SALES_REGISTER))->toBeTrue();

    $this->actingAs($admin)
        ->get(route('users.edit', $staff))
        ->assertInertia(fn ($page) => $page->where('canViewSalesRegister', true));

    $this->actingAs($admin)->put(route('users.update', $staff), $payload(false))->assertRedirect(route('users.index'));
    expect($staff->fresh()->hasPermissionTo(User::PERMISSION_VIEW_SALES_REGISTER))->toBeFalse();
});

it('can grant the permission when creating a user', function () {
    $admin = registerUserWithRole('admin');
    Role::findOrCreate('staff');

    $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Store Supervisor',
        'email' => 'supervisor@example.com',
        'password' => 'password123',
        'roles' => ['staff'],
        'status' => true,
        'can_view_sales_register' => true,
    ])->assertRedirect(route('users.index'));

    expect(User::where('email', 'supervisor@example.com')->first()->hasPermissionTo(User::PERMISSION_VIEW_SALES_REGISTER))->toBeTrue();
});
