<?php

namespace Tests\Feature\Api;

use App\Enums\MobileNotificationRecipientStatus;
use App\Enums\MobileNotificationStatus;
use App\Models\DeviceToken;
use App\Models\MobileNotification;
use App\Models\MobileNotificationAttachment;
use App\Models\MobileNotificationRecipient;
use App\Models\User;
use App\Services\Authorization\RbacInitializationService;
use App\Services\Notifications\FcmNotificationService;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class MobileNotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private function seedRoles(): void
    {
        app(RbacInitializationService::class)->seed();
    }

    private function createNotificationWithRecipient(User $user, array $notificationAttrs = [], int $attachmentCount = 0): array
    {
        $admin = User::factory()->create();
        $notification = MobileNotification::create(array_merge([
            'title' => 'Test notification',
            'body' => 'Test body content',
            'status' => MobileNotificationStatus::SENT->value,
            'created_by' => $admin->id,
            'queued_at' => now(),
            'sent_at' => now(),
        ], $notificationAttrs));

        $recipient = MobileNotificationRecipient::create([
            'mobile_notification_id' => $notification->id,
            'user_id' => $user->id,
            'status' => MobileNotificationRecipientStatus::SENT->value,
            'device_count' => 1,
            'delivered_devices_count' => 1,
            'sent_at' => now(),
        ]);

        for ($i = 0; $i < $attachmentCount; $i++) {
            MobileNotificationAttachment::create([
                'mobile_notification_id' => $notification->id,
                'disk' => 'public',
                'path' => "mobile-notification-attachments/{$notification->id}/file{$i}.jpg",
                'original_name' => "photo{$i}.jpg",
                'mime_type' => 'image/jpeg',
                'size' => 12345,
                'type' => 'image',
            ]);
        }

        return [$notification, $recipient];
    }

    public function test_notification_list_returns_attachment_count(): void
    {
        $this->seedRoles();
        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);
        Sanctum::actingAs($employee);

        $this->createNotificationWithRecipient($employee, attachmentCount: 3);
        $this->createNotificationWithRecipient($employee, ['title' => 'Second'], attachmentCount: 0);

        $response = $this->getJson('/api/mobile/notifications');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.notifications');

        $notifications = $response->json('data.notifications');
        $withAttachments = collect($notifications)->firstWhere('title', 'Test notification');
        $withoutAttachments = collect($notifications)->firstWhere('title', 'Second');

        $this->assertSame(3, $withAttachments['attachment_count']);
        $this->assertNotNull($withAttachments['first_image_url']);
        $this->assertSame(0, $withoutAttachments['attachment_count']);
    }

    public function test_notification_details_returns_attachments(): void
    {
        $this->seedRoles();
        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);
        Sanctum::actingAs($employee);

        [$notification] = $this->createNotificationWithRecipient($employee, attachmentCount: 2);

        $response = $this->getJson("/api/mobile/notifications/{$notification->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.notification.id', $notification->id)
            ->assertJsonPath('data.notification.title', 'Test notification')
            ->assertJsonCount(2, 'data.notification.attachments');

        $attachment = $response->json('data.notification.attachments.0');
        $this->assertSame('image', $attachment['type']);
        $this->assertSame('photo0.jpg', $attachment['original_name']);
        $this->assertArrayHasKey('url', $attachment);
    }

    public function test_notification_details_marks_as_read(): void
    {
        $this->seedRoles();
        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);
        Sanctum::actingAs($employee);

        [$notification, $recipient] = $this->createNotificationWithRecipient($employee);

        $this->assertNull($recipient->fresh()->read_at);

        $this->getJson("/api/mobile/notifications/{$notification->id}");

        $this->assertNotNull($recipient->fresh()->read_at);
    }

    public function test_unauthorized_user_cannot_access_another_users_notification(): void
    {
        $this->seedRoles();
        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);
        $otherEmployee = User::factory()->create();
        $otherEmployee->assignRole(Rbac::EMPLOYEE);
        Sanctum::actingAs($otherEmployee);

        [$notification] = $this->createNotificationWithRecipient($employee);

        $response = $this->getJson("/api/mobile/notifications/{$notification->id}");

        $response->assertStatus(404);
    }

    public function test_admin_can_create_notification_with_attachments(): void
    {
        $this->seedRoles();
        Storage::fake('public');

        $admin = User::factory()->create();
        $admin->assignRole(Rbac::ADMIN);
        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);
        DeviceToken::create([
            'user_id' => $employee->id,
            'device_id' => 'dev-1',
            'fcm_token' => 'token-1',
            'device_type' => 'android',
        ]);

        $this->mock(FcmNotificationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('countTokensForUser')->andReturn(1);
            $mock->shouldReceive('sendNotificationToUser')->andReturn(1);
        });

        $notification = app(\App\Services\Notifications\MobileNotificationService::class)
            ->createQueuedNotification([
                'title' => 'With attachments',
                'body' => 'Has files',
                'send_to_all' => true,
            ], $admin);

        $file = UploadedFile::fake()->image('test.jpg', 100, 100)->size(500);
        $storedPath = $file->store("mobile-notification-attachments/{$notification->id}", 'public');

        $notification->attachments()->create([
            'disk' => 'public',
            'path' => $storedPath,
            'original_name' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'size' => $file->getSize(),
            'type' => 'image',
        ]);

        $this->assertSame(1, $notification->attachments()->count());
        $attachment = $notification->attachments()->first();
        $this->assertSame('image', $attachment->type);
        $this->assertSame('test.jpg', $attachment->original_name);
        Storage::disk('public')->assertExists($storedPath);
    }

    public function test_fcm_payload_includes_notification_id(): void
    {
        $this->seedRoles();
        Queue::fake();

        $admin = User::factory()->create();
        $admin->assignRole(Rbac::ADMIN);
        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);
        DeviceToken::create([
            'user_id' => $employee->id,
            'device_id' => 'dev-1',
            'fcm_token' => 'token-1',
            'device_type' => 'android',
        ]);

        $notification = app(\App\Services\Notifications\MobileNotificationService::class)
            ->createQueuedNotification([
                'title' => 'FCM test',
                'body' => 'Payload check',
                'send_to_all' => true,
            ], $admin);

        $this->mock(FcmNotificationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('countTokensForUser')->andReturn(1);
            $mock->shouldReceive('sendNotificationToUser')
                ->once()
                ->withArgs(function (User $recipient, string $title, string $body, array $data): bool {
                    return isset($data['notification_id'])
                        && isset($data['route'])
                        && str_starts_with($data['route'], '/notifications/');
                })
                ->andReturn(1);
        });

        (new \App\Jobs\SendMobileNotificationJob($notification->id))->handle(
            app(\App\Services\Notifications\MobileNotificationAudienceResolver::class),
            app(\App\Services\Notifications\MobileNotificationService::class),
            app(FcmNotificationService::class),
        );
    }
}
