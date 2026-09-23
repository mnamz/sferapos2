<?php

use App\Models\User;

it('shows the sample login only when demo mode is on', function () {
    config(['app.demo.enabled' => false]);
    $this->get(route('login'))->assertInertia(fn ($page) => $page->where('demoLogin', null));

    config(['app.demo.enabled' => true, 'app.demo.email' => 'demo@sfera.my', 'app.demo.password' => 'demo1234']);
    $this->get(route('login'))->assertInertia(fn ($page) => $page
        ->where('demoLogin.email', 'demo@sfera.my')
        ->where('demoLogin.password', 'demo1234'));
});

it('stops the demo account from managing users or its password', function () {
    config(['app.demo.enabled' => true, 'app.demo.email' => 'demo@sfera.my']);
    \Spatie\Permission\Models\Role::findOrCreate('admin');
    $demo = User::factory()->create(['email' => 'demo@sfera.my', 'status' => true]);
    $demo->assignRole('admin');
    $owner = User::factory()->create(['status' => true]);

    $this->actingAs($demo)->delete(route('users.destroy', $owner))->assertSessionHas('error');
    expect($owner->fresh())->not->toBeNull();

    $this->actingAs($demo)->put(route('password.update'), [
        'current_password' => 'password', 'password' => 'hijacked1', 'password_confirmation' => 'hijacked1',
    ])->assertSessionHas('error');

    // Normal POS work is still allowed.
    $this->actingAs($demo)->get(route('repairs.index'))->assertOk();
});
