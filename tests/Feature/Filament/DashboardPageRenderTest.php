<?php

namespace Tests\Feature\Filament;

use App\Enums\TaskStatus;
use App\Filament\Dashboard\DashboardPage;
use App\Models\Task;
use App\Models\User;
use App\Services\Authorization\RbacInitializationService;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPageRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_render_dashboard_with_clock_and_stats(): void
    {
        app(RbacInitializationService::class)->seed();

        $admin = User::factory()->create();
        $admin->assignRole(Rbac::ADMIN);

        Task::factory()->create([
            'status' => TaskStatus::NEW->value,
        ]);

        Task::factory()->create([
            'status' => TaskStatus::COMPLETED->value,
        ]);

        app()->setLocale('ar');

        $this->actingAs($admin)
            ->get(DashboardPage::getUrl())
            ->assertOk()
            ->assertSee('data-dashboard-clock', false)
            ->assertSee('data-dashboard-date', false)
            ->assertSee('data-dashboard-time', false)
            ->assertSee('data-dashboard-stats', false)
            ->assertSee('إجمالي المهام')
            ->assertSee('مكتملة');
    }
}
