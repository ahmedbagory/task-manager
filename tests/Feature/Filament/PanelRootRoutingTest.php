<?php

namespace Tests\Feature\Filament;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Services\Authorization\RbacInitializationService;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelRootRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_root_redirects_to_root_login(): void
    {
        $this->get('/')
            ->assertRedirect('/login');
    }

    public function test_root_login_route_renders_filament_login_page(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('الدخول إلى حسابك');
    }

    public function test_legacy_admin_paths_redirect_to_new_root_paths(): void
    {
        $this->get('/admin')
            ->assertRedirect('/');

        $this->get('/admin/login')
            ->assertRedirect('/login');
    }

    public function test_authenticated_user_can_open_dashboard_from_root_path(): void
    {
        app(RbacInitializationService::class)->seed();

        $admin = User::factory()->create();
        $admin->assignRole(Rbac::ADMIN);

        Task::factory()->create([
            'status' => TaskStatus::NEW->value,
        ]);

        $this->actingAs($admin)
            ->get('/')
            ->assertOk()
            ->assertSee('data-dashboard-clock', false)
            ->assertDontSee('Laravel');
    }
}
