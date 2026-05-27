<?php

namespace Tests\Feature\Filament;

use App\Enums\TaskStatus;
use App\Enums\WhatsappMessageDirection;
use App\Filament\Dashboard\DashboardPage;
use App\Models\Task;
use App\Models\TaskAssignmentHistory;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\Authorization\RbacInitializationService;
use App\Services\WhatsApp\WhatsappBridgeStatusService;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPageRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_sees_personal_dashboard(): void
    {
        app(RbacInitializationService::class)->seed();

        $employee = User::factory()->create(['name' => 'أحمد الموظف']);
        $employee->assignRole(Rbac::EMPLOYEE);

        $otherUser = User::factory()->create(['name' => 'مستخدم آخر']);
        $otherUser->assignRole(Rbac::EMPLOYEE);

        Task::factory()->create([
            'title' => 'مهمة جديدة للموظف',
            'assigned_to_user_id' => $employee->id,
            'status' => TaskStatus::NEW->value,
        ]);

        Task::factory()->create([
            'title' => 'مهمة قيد التنفيذ',
            'assigned_to_user_id' => $employee->id,
            'status' => TaskStatus::IN_PROGRESS->value,
        ]);

        Task::factory()->create([
            'title' => 'مهمة متأخرة',
            'assigned_to_user_id' => $employee->id,
            'status' => TaskStatus::ACCEPTED->value,
            'due_at' => now()->subDay(),
        ]);

        Task::factory()->create([
            'title' => 'مهمة بانتظار التأكيد',
            'assigned_to_user_id' => $employee->id,
            'status' => TaskStatus::AWAITING_REPORTER_CONFIRMATION->value,
        ]);

        Task::factory()->create([
            'title' => 'مهمة لا يجب أن تظهر',
            'assigned_to_user_id' => $otherUser->id,
            'status' => TaskStatus::NEW->value,
        ]);

        app()->setLocale('ar');

        $this->actingAs($employee)
            ->get(DashboardPage::getUrl())
            ->assertOk()
            ->assertSee('data-dashboard-role="employee"', false)
            ->assertSee('مرحبًا، أحمد الموظف')
            ->assertSee('مهامي الجديدة')
            ->assertSee('مهامي قيد التنفيذ')
            ->assertSee('مهامي المتأخرة')
            ->assertSee('بانتظار تأكيد صاحب الطلب')
            ->assertSee('آخر مهامي')
            ->assertSee('data-dashboard-clock', false)
            ->assertSee('data-dashboard-date', false)
            ->assertSee('data-dashboard-time', false)
            ->assertDontSee('يحتاج قرار الآن')
            ->assertDontSee('مهمة لا يجب أن تظهر');
    }

    public function test_admin_sees_management_dashboard_with_whatsapp_operations(): void
    {
        app(RbacInitializationService::class)->seed();

        $admin = User::factory()->create(['name' => 'مدير النظام']);
        $admin->assignRole(Rbac::ADMIN);

        $employee = User::factory()->create(['name' => 'موظف التشغيل']);
        $employee->assignRole(Rbac::EMPLOYEE);

        $assignedTask = Task::factory()->create([
            'title' => 'مهمة تم إسنادها',
            'assigned_to_user_id' => $employee->id,
            'status' => TaskStatus::IN_PROGRESS->value,
        ]);

        Task::factory()->create([
            'title' => 'مهمة بانتظار الإسناد',
            'status' => TaskStatus::PENDING_ASSIGNMENT->value,
            'assigned_to_user_id' => null,
        ]);

        Task::factory()->create([
            'title' => 'مهمة متأخرة جدًا',
            'assigned_to_user_id' => $employee->id,
            'status' => TaskStatus::ACCEPTED->value,
            'due_at' => now()->subDays(4),
        ]);

        Task::factory()->create([
            'title' => 'مهمة بانتظار رد صاحب الطلب',
            'status' => TaskStatus::AWAITING_REPORTER_CONFIRMATION->value,
            'resolution_submitted_at' => now()->subDays(3),
        ]);

        Task::factory()->create([
            'title' => 'مهمة معاد فتحها',
            'assigned_to_user_id' => $employee->id,
            'status' => TaskStatus::REOPENED->value,
        ]);

        Task::factory()->create([
            'title' => 'مهمتي أنا',
            'assigned_to_user_id' => $admin->id,
            'status' => TaskStatus::NEW->value,
        ]);

        $completedTask = Task::factory()->create([
            'title' => 'مهمة مكتملة اليوم',
            'status' => TaskStatus::COMPLETED->value,
            'completed_at' => now(),
        ]);

        TaskAssignmentHistory::query()->create([
            'task_id' => $assignedTask->id,
            'action' => 'assigned',
            'to_user_id' => $employee->id,
            'performed_by' => $admin->id,
        ]);

        WhatsappMessage::factory()->create([
            'task_id' => $assignedTask->id,
            'direction' => WhatsappMessageDirection::INBOUND->value,
            'body' => 'رسالة واتساب تحولت لمهمة',
            'received_at' => now(),
        ]);

        WhatsappMessage::factory()->create([
            'task_id' => null,
            'direction' => WhatsappMessageDirection::INBOUND->value,
            'body' => 'رسالة غير معالجة',
            'received_at' => now()->subMinute(),
        ]);

        $this->fakeWhatsappBridgeStatus();

        app()->setLocale('ar');

        $this->actingAs($admin)
            ->get(DashboardPage::getUrl())
            ->assertOk()
            ->assertSee('data-dashboard-role="management"', false)
            ->assertSee('مرحبًا، مدير النظام')
            ->assertSee('يحتاج قرار الآن')
            ->assertSee('توزيع العمل على الموظفين')
            ->assertSee('الأقسام الأكثر طلبًا')
            ->assertSee('عمليات واتساب')
            ->assertSee('إعادة تشغيل البريدج')
            ->assertSee('إعادة الربط')
            ->assertSee('عرض QR')
            ->assertSee('آخر النشاط')
            ->assertSee('مهامي أنا')
            ->assertSee('رسالة واتساب تحولت لمهمة')
            ->assertSee('مهمة مكتملة اليوم')
            ->assertSee('data-dashboard-clock', false)
            ->assertSee('data-dashboard-date', false)
            ->assertSee('data-dashboard-time', false);
    }

    private function fakeWhatsappBridgeStatus(): void
    {
        app()->instance(WhatsappBridgeStatusService::class, new class
        {
            /**
             * @return array<string, mixed>
             */
            public function current(): array
            {
                return [
                    'state' => 'ready',
                    'label' => 'متصل',
                    'badge' => 'success',
                    'status_hint' => 'الجلسة جاهزة لإرسال الرسائل واستقبالها.',
                    'supports_bridge' => true,
                    'outbound_enabled' => true,
                    'can_send' => true,
                    'can_queue' => false,
                    'account_id' => '201234567890',
                    'account_name' => 'Main Session',
                    'group_name' => null,
                    'groups_count' => 8,
                    'qr_available' => true,
                    'last_heartbeat_at' => now()->toIso8601String(),
                    'last_message_at' => now()->toIso8601String(),
                    'pm2_status' => 'online',
                    'pm2_found' => true,
                    'auth_exists' => true,
                    'restart_requested' => false,
                ];
            }
        });
    }
}
