<?php

namespace App\Filament\Resources\WhatsappMessages\Actions;

use App\Exceptions\WhatsAppMessageAlreadyConvertedException;
use App\Filament\Resources\Tasks\TaskResource;
use App\Filament\Resources\WhatsappMessages\Schemas\ConvertWhatsappMessageToTaskForm;
use App\Models\User;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use App\Services\WhatsApp\WhatsAppInboxService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Throwable;

class ConvertWhatsappMessageToTaskAction
{
    public static function make(): Action
    {
        return Action::make('convertToTask')
            ->label('تحويل لمهمة')
            ->icon('heroicon-o-clipboard-document-list')
            ->color('success')
            ->visible(fn (WhatsappMessage $record): bool => blank($record->task_id))
            ->authorize(fn (WhatsappMessage $record): bool => auth()->user()?->can('convertToTask', $record) ?? false)
            ->schema(ConvertWhatsappMessageToTaskForm::schema())
            ->fillForm(function (WhatsappMessage $record): array {
                $record->loadMissing('contact');
                $routingContact = $record->contact;

                if ($routingContact === null || (blank($routingContact->department_id) && blank($routingContact->default_location))) {
                    $participantJid = Arr::get($record->raw_payload, 'payload.raw_payload.key.participantPn')
                        ?? Arr::get($record->raw_payload, 'payload.raw_payload.key.participantPN')
                        ?? Arr::get($record->raw_payload, 'payload.raw_payload.key.participant_pn')
                        ?? Arr::get($record->raw_payload, 'bridge_payload.key.participantPn')
                        ?? Arr::get($record->raw_payload, 'bridge_payload.key.participantPN')
                        ?? Arr::get($record->raw_payload, 'bridge_payload.key.participant_pn');

                    $participantPhone = str($participantJid)
                        ->before('@')
                        ->trim()
                        ->ltrim('+')
                        ->toString();

                    if ($participantPhone !== '') {
                        $routingContact = WhatsappContact::query()
                            ->whereIn('phone', [$participantPhone, '+'.$participantPhone])
                            ->first() ?? $routingContact;
                    }
                }

                $messageBody = trim((string) ($record->body ?? ''));

                return [
                    'title' => str($messageBody !== '' ? $messageBody : 'مرفق من واتساب')->limit(100)->toString(),
                    'description' => $messageBody !== '' ? $messageBody : ($record->hasMedia() ? 'مرفق من واتساب' : null),
                    'priority' => 'medium',
                    'reported_by_user_id' => $routingContact?->user_id,
                    'department_id' => $routingContact?->department_id,
                    'location' => $routingContact?->default_location,
                ];
            })
            ->modalHeading('تحويل رسالة واتساب إلى مهمة')
            ->modalSubmitActionLabel('إنشاء المهمة')
            ->modalWidth('3xl')
            ->action(function (array $data, WhatsappMessage $record, WhatsAppInboxService $inboxService, Component $livewire): void {
                /** @var User $actor */
                $actor = auth()->user();

                try {
                    $task = $inboxService->convertMessageToTask(
                        message: $record,
                        data: $data,
                        actor: $actor,
                    );

                    Notification::make()
                        ->title(__('Task :task_number created from WhatsApp message.', ['task_number' => $task->displayNumber()]))
                        ->success()
                        ->send();

                    $livewire->redirect(TaskResource::getUrl('view', ['record' => $task]), navigate: true);
                } catch (WhatsAppMessageAlreadyConvertedException $exception) {
                    Notification::make()
                        ->title(__('This message is already linked to task :task_number.', ['task_number' => $exception->task->displayNumber()]))
                        ->warning()
                        ->send();

                    $livewire->redirect(TaskResource::getUrl('view', ['record' => $exception->task]), navigate: true);
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->title(self::getValidationErrorMessage($exception))
                        ->danger()
                        ->send();
                } catch (Throwable $exception) {
                    report($exception);

                    Notification::make()
                        ->title(__('Unable to convert this message to a task right now.'))
                        ->danger()
                        ->send();
                }
            });
    }

    public static function makeViewLinkedTask(): Action
    {
        return Action::make('viewLinkedTask')
            ->label('فتح المهمة')
            ->icon('heroicon-o-link')
            ->color('gray')
            ->visible(fn (WhatsappMessage $record): bool => filled($record->task_id))
            ->authorize(function (WhatsappMessage $record): bool {
                if (! filled($record->task_id) || ! $record->task) {
                    return false;
                }

                return auth()->user()?->can('view', $record->task) ?? false;
            })
            ->url(fn (WhatsappMessage $record): ?string => $record->task
                ? TaskResource::getUrl('view', ['record' => $record->task])
                : null);
    }

    private static function getValidationErrorMessage(ValidationException $exception): string
    {
        $firstError = collect($exception->errors())->flatten()->first();

        if (is_string($firstError) && $firstError !== '') {
            return $firstError;
        }

        return __('Validation failed while converting this message.');
    }
}
