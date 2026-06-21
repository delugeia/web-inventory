<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppLayoutHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_slot_header_still_renders(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<header class="bg-white shadow">', false)
            ->assertSee('Dashboard');
    }
}
