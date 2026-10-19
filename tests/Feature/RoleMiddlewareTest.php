<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'role:admin'])->get('/test-admin-only', function () {
            return 'admin-ok';
        });

        Route::middleware(['web', 'role:agent'])->get('/test-agent-only', function () {
            return 'agent-ok';
        });

        Route::middleware(['web', 'role:admin,agent'])->get('/test-staff-only', function () {
            return 'staff-ok';
        });
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/test-admin-only');

        $response->assertRedirect('/login');
    }

    public function test_customer_cannot_access_admin_route(): void
    {
        $customer = User::factory()->customer()->create();

        $response = $this->actingAs($customer)->get('/test-admin-only');

        $response->assertForbidden();
    }

    public function test_agent_cannot_access_admin_route(): void
    {
        $agent = User::factory()->agent()->create();

        $response = $this->actingAs($agent)->get('/test-admin-only');

        $response->assertForbidden();
    }

    public function test_admin_can_access_admin_route(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/test-admin-only');

        $response->assertOk();
        $response->assertSee('admin-ok');
    }

    public function test_both_admin_and_agent_can_access_staff_route(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->agent()->create();
        $customer = User::factory()->customer()->create();

        $this->actingAs($admin)->get('/test-staff-only')->assertOk();
        $this->actingAs($agent)->get('/test-staff-only')->assertOk();
        $this->actingAs($customer)->get('/test-staff-only')->assertForbidden();
    }
}
