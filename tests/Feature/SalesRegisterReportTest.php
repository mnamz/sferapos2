<?php

use App\Models\User;

it('renders the sales register report page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('reports.sales-register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/SalesRegister')
            ->has('groups')
            ->has('grandTotal')
            ->has('filterOptions')
            ->has('filters')
        );
});
