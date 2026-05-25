<?php

namespace App\Http\Controllers\MyTasks;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\MyTasks\ConfirmTaskResolutionRequest;
use App\Http\Requests\MyTasks\RejectTaskAssignmentRequest;
use App\Http\Requests\MyTasks\RejectTaskResolutionRequest;
use App\Http\Requests\MyTasks\StoreTaskAttachmentRequest;
use App\Http\Requests\MyTasks\StoreTaskCommentRequest;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Services\Tasks\TaskAccessService;
use App\Services\Tasks\TaskAssignmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MyTaskWorkspaceController extends Controller
{
    public function __construct(
        private readonly TaskAccessService $taskAccessService,
    ) {}

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $selectedStatus = (string) $request->query('status', '');
        $statusValues = TaskStatus::values();

        $tasksQuery = Task::query()
            ->with(['department.parent', 'category'])
            ->orderByDesc('updated_at');

        $this->taskAccessService->applyVisibleToUserScope($tasksQuery, $user);

        if (in_array($selectedStatus, $statusValues, true)) {
            $tasksQuery->where('status', $selectedStatus);
        } else {
            $selectedStatus = '';
        }

        $tasks = $tasksQuery
            ->paginate(12)
            ->withQueryString();

        return view('my-tasks.index', [
            'tasks' => $tasks,
            'statusOptions' => TaskStatus::options(),
            'selectedStatus' => $selectedStatus,
        ]);
    }

    public function show(Request $request, Task $task): View
    {
        $this->authorize('viewAssignedWorkspace', $task);

        /** @var User $user */
        $user = $request->user();

        $task->load([
            'department.parent',
            'category',
            'assignments.assignedByUser',
            'assignments.assignedToUser',
            'comments.user',
            'attachments.user',
        ]);

        return view('my-tasks.show', [
            'task' => $task,
            'currentAssignment' => $this->resolveUserAssignment($task, $user, abortIfMissing: false),
            'timeline' => $this->buildTimeline($task),
            'canRespond' => $user->can('respondToAssignment', $task),
            'canComment' => $user->can('addWorkspaceComment', $task),
            'canUploadAttachment' => $user->can('uploadWorkspaceAttachment', $task),
            'canConfirmResolution' => $user->can('confirmResolution', $task),
            'canRejectResolution' => $user->can('rejectResolution', $task),
            'currentUserRole' => $this->taskAccessService->resolveCurrentUserRole($task, $user),
            'assignees' => $this->taskAccessService->resolveAssignees($task),
            'reporter' => $this->taskAccessService->resolveReporter($task),
        ]);
    }

    public function accept(Request $request, Task $task, TaskAssignmentService $taskAssignmentService): RedirectResponse
    {
        $this->authorize('respondToAssignment', $task);

        /** @var User $user */
        $user = $request->user();

        $assignment = $this->resolveUserAssignment($task, $user, abortIfMissing: false);

        if (! $assignment) {
            return $this->claimTargetedTask($task, $user);
        }

        try {
            $taskAssignmentService->acceptAssignment($assignment, $user);
        } catch (ValidationException $exception) {
            return $this->redirectBackWithValidationError($exception);
        }

        return back()->with('status', __('Task accepted successfully.'));
    }

    public function start(Request $request, Task $task, TaskAssignmentService $taskAssignmentService): RedirectResponse
    {
        $this->authorize('respondToAssignment', $task);

        /** @var User $user */
        $user = $request->user();

        try {
            $taskAssignmentService->startTask($this->resolveUserAssignment($task, $user), $user);
        } catch (ValidationException $exception) {
            return $this->redirectBackWithValidationError($exception);
        }

        return back()->with('status', __('Work started.'));
    }

    public function complete(Request $request, Task $task, TaskAssignmentService $taskAssignmentService): RedirectResponse
    {
        $this->authorize('respondToAssignment', $task);

        /** @var User $user */
        $user = $request->user();

        try {
            $taskAssignmentService->completeAssignedTask($this->resolveUserAssignment($task, $user), $user);
        } catch (ValidationException $exception) {
            return $this->redirectBackWithValidationError($exception);
        }

        return back()->with('status', __('Task submitted for reporter confirmation.'));
    }

    public function waitResponse(Request $request, Task $task, TaskAssignmentService $taskAssignmentService): RedirectResponse
    {
        $this->authorize('respondToAssignment', $task);

        /** @var User $user */
        $user = $request->user();

        try {
            $taskAssignmentService->waitResponseTask($this->resolveUserAssignment($task, $user), $user);
        } catch (ValidationException $exception) {
            return $this->redirectBackWithValidationError($exception);
        }

        return back()->with('status', __('Task moved to waiting response.'));
    }

    public function resume(Request $request, Task $task, TaskAssignmentService $taskAssignmentService): RedirectResponse
    {
        $this->authorize('respondToAssignment', $task);

        /** @var User $user */
        $user = $request->user();

        try {
            $taskAssignmentService->resumeTask($this->resolveUserAssignment($task, $user), $user);
        } catch (ValidationException $exception) {
            return $this->redirectBackWithValidationError($exception);
        }

        return back()->with('status', __('Task resumed successfully.'));
    }

    public function reject(
        RejectTaskAssignmentRequest $request,
        Task $task,
        TaskAssignmentService $taskAssignmentService
    ): RedirectResponse {
        $this->authorize('respondToAssignment', $task);

        /** @var User $user */
        $user = $request->user();

        try {
            $taskAssignmentService->rejectAssignment(
                assignment: $this->resolveUserAssignment($task, $user),
                actor: $user,
                reason: (string) $request->validated('reason'),
            );
        } catch (ValidationException $exception) {
            return $this->redirectBackWithValidationError($exception);
        }

        return redirect()
            ->route('my-tasks.index')
            ->with('status', __('Task rejected successfully.'));
    }

    public function storeComment(StoreTaskCommentRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('addWorkspaceComment', $task);

        /** @var User $user */
        $user = $request->user();

        $task->comments()->create([
            'user_id' => $user->id,
            'comment' => (string) $request->validated('comment'),
            'is_internal' => false,
        ]);

        return back()->with('status', __('Comment added.'));
    }

    public function storeAttachment(StoreTaskAttachmentRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('uploadWorkspaceAttachment', $task);

        /** @var User $user */
        $user = $request->user();

        $file = $request->file('attachment');
        $storedPath = $file->store("task-attachments/{$task->id}", 'local');

        $task->attachments()->create([
            'user_id' => $user->id,
            'disk' => 'local',
            'path' => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'type' => \App\Models\TaskAttachment::resolveType($file->getMimeType()),
        ]);

        return back()->with('status', __('Attachment uploaded.'));
    }

    public function confirmResolution(
        ConfirmTaskResolutionRequest $request,
        Task $task,
        TaskAssignmentService $taskAssignmentService
    ): RedirectResponse {
        $this->authorize('confirmResolution', $task);

        /** @var User $user */
        $user = $request->user();

        try {
            $taskAssignmentService->confirmResolution($task, $user, $request->validated('comment'));
        } catch (ValidationException $exception) {
            return $this->redirectBackWithValidationError($exception);
        }

        return back()->with('status', __('Task resolution confirmed.'));
    }

    public function rejectResolution(
        RejectTaskResolutionRequest $request,
        Task $task,
        TaskAssignmentService $taskAssignmentService
    ): RedirectResponse {
        $this->authorize('rejectResolution', $task);

        /** @var User $user */
        $user = $request->user();

        try {
            $taskAssignmentService->rejectResolution($task, $user, (string) $request->validated('comment'));
        } catch (ValidationException $exception) {
            return $this->redirectBackWithValidationError($exception);
        }

        return back()->with('status', __('Task returned for follow-up.'));
    }

    public function downloadAttachment(Request $request, Task $task, TaskAttachment $attachment): StreamedResponse
    {
        $this->authorize('viewAssignedWorkspace', $task);

        /** @var User $user */
        $user = $request->user();

        if ($attachment->task_id !== $task->id || $user->cannot('viewAttachments', $task)) {
            abort(403);
        }

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name ?: basename($attachment->path)
        );
    }

    public function previewAttachment(Request $request, TaskAttachment $attachment): StreamedResponse
    {
        abort_unless($this->canAccessAttachment($request, $attachment), 403);

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        return response()->stream(
            fn () => fpassthru($disk->readStream($attachment->path)),
            200,
            [
                'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.($attachment->original_name ?: basename($attachment->path)).'"',
                'Cache-Control' => 'private, max-age=3600',
            ]
        );
    }

    public function downloadAnyAttachment(Request $request, TaskAttachment $attachment): StreamedResponse
    {
        abort_unless($this->canAccessAttachment($request, $attachment), 403);

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name ?: basename($attachment->path)
        );
    }

    private function canAccessAttachment(Request $request, TaskAttachment $attachment): bool
    {
        /** @var User $user */
        $user = $request->user();

        $task = $attachment->task;

        return $task && $user->can('viewAttachments', $task);
    }

    private function resolveUserAssignment(Task $task, User $user, bool $abortIfMissing = true): ?TaskAssignment
    {
        $assignment = $this->taskAccessService->resolveUserAssignment($task, $user);

        if (! $assignment && $abortIfMissing) {
            abort(403);
        }

        return $assignment;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildTimeline(Task $task): Collection
    {
        $events = collect();

        foreach ($task->assignments as $assignment) {
            $events->push([
                'at' => $assignment->assigned_at ?? $assignment->created_at,
                'type' => 'assignment',
                'title' => __('Assignment: :status', ['status' => $assignment->status->label()]),
                'meta' => trim(implode(' | ', array_filter([
                    __('Assigned To: :name', ['name' => $assignment->assignedToUser?->name ?? __('N/A')]),
                    __('Assigned By: :name', ['name' => $assignment->assignedByUser?->name ?? __('System')]),
                ]))),
                'body' => $assignment->note,
            ]);

            if ($assignment->accepted_at) {
                $events->push([
                    'at' => $assignment->accepted_at,
                    'type' => 'assignment',
                    'title' => __('Assignment accepted'),
                    'meta' => __('By: :name', ['name' => $assignment->assignedToUser?->name ?? __('N/A')]),
                    'body' => null,
                ]);
            }

            if ($assignment->completed_at) {
                $events->push([
                    'at' => $assignment->completed_at,
                    'type' => 'assignment',
                    'title' => __('Assignment completed'),
                    'meta' => __('By: :name', ['name' => $assignment->assignedToUser?->name ?? __('N/A')]),
                    'body' => null,
                ]);
            }
        }

        foreach ($task->comments as $comment) {
            $events->push([
                'at' => $comment->created_at,
                'type' => 'comment',
                'title' => __('Comment'),
                'meta' => __('By: :name', ['name' => $comment->user?->name ?? __('Unknown')]),
                'body' => $comment->comment,
            ]);
        }

        foreach ($task->attachments as $attachment) {
            $events->push([
                'at' => $attachment->created_at,
                'type' => 'attachment',
                'title' => __('Attachment uploaded'),
                'meta' => __('By: :name', ['name' => $attachment->user?->name ?? __('Unknown')]),
                'body' => $attachment->original_name,
            ]);
        }

        return $events->sortByDesc('at')->values();
    }

    private function redirectBackWithValidationError(ValidationException $exception): RedirectResponse
    {
        return back()
            ->withErrors($exception->errors())
            ->withInput();
    }

    private function claimTargetedTask(Task $task, User $user): RedirectResponse
    {
        try {
            DB::transaction(function () use ($task, $user): void {
                $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);

                if (in_array($lockedTask->status->value, [
                    TaskStatus::COMPLETED->value,
                    TaskStatus::CANCELLED->value,
                ], true)) {
                    throw ValidationException::withMessages([
                        'task' => ['Task is already completed or cancelled.'],
                    ]);
                }

                $activeExists = $lockedTask->assignments()
                    ->whereIn('status', [TaskAssignmentStatus::ASSIGNED->value, TaskAssignmentStatus::ACCEPTED->value])
                    ->exists();

                if ($activeExists) {
                    throw ValidationException::withMessages([
                        'task' => ['Task already has an active assignment.'],
                    ]);
                }

                $lockedTask->assignments()->create([
                    'assigned_to_user_id' => $user->id,
                    'assigned_by_user_id' => $user->id,
                    'status' => TaskAssignmentStatus::ACCEPTED->value,
                    'assigned_at' => now(),
                    'accepted_at' => now(),
                ]);

                $lockedTask->forceFill([
                    'assigned_to_user_id' => $user->id,
                    'status' => TaskStatus::ACCEPTED->value,
                    'updated_by' => $user->id,
                ])->save();

                TaskAssignmentHistory::query()->create([
                    'task_id' => $lockedTask->id,
                    'action' => 'accepted',
                    'to_user_id' => $user->id,
                    'performed_by' => $user->id,
                ]);
            });
        } catch (ValidationException $exception) {
            return $this->redirectBackWithValidationError($exception);
        }

        return back()->with('status', __('Task accepted successfully.'));
    }
}
