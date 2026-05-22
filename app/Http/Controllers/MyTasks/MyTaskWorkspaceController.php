<?php

namespace App\Http\Controllers\MyTasks;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\MyTasks\RejectTaskAssignmentRequest;
use App\Http\Requests\MyTasks\StoreTaskAttachmentRequest;
use App\Http\Requests\MyTasks\StoreTaskCommentRequest;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskAttachment;
use App\Models\User;
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
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $selectedStatus = (string) $request->query('status', '');
        $statusValues = TaskStatus::values();

        $tasksQuery = Task::query()
            ->where('assigned_to_user_id', $user->id)
            ->with(['department.parent', 'category'])
            ->orderByDesc('updated_at');

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
            'currentAssignment' => $this->resolveUserAssignment($task, $user),
            'timeline' => $this->buildTimeline($task),
            'canRespond' => $user->can('respondToAssignment', $task),
            'canComment' => $user->can('addWorkspaceComment', $task),
            'canUploadAttachment' => $user->can('uploadWorkspaceAttachment', $task),
        ]);
    }

    public function accept(Request $request, Task $task, TaskAssignmentService $taskAssignmentService): RedirectResponse
    {
        $this->authorize('respondToAssignment', $task);

        /** @var User $user */
        $user = $request->user();

        try {
            $taskAssignmentService->acceptAssignment($this->resolveUserAssignment($task, $user), $user);
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

        return back()->with('status', __('Task marked as completed.'));
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

        return back()->with('status', __('Task rejected successfully.'));
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
            'type' => str_starts_with((string) $file->getMimeType(), 'image/') ? 'image' : 'file',
        ]);

        return back()->with('status', __('Attachment uploaded.'));
    }

    public function downloadAttachment(Request $request, Task $task, TaskAttachment $attachment): StreamedResponse
    {
        $this->authorize('viewAssignedWorkspace', $task);

        /** @var User $user */
        $user = $request->user();

        if ($task->assigned_to_user_id !== $user->id || $attachment->task_id !== $task->id) {
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
                'Content-Disposition' => 'inline; filename="' . ($attachment->original_name ?: basename($attachment->path)) . '"',
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

        if ($user->hasRole(\App\Support\Rbac::SUPER_ADMIN)) {
            return true;
        }

        $task = $attachment->task;

        return $task && $task->assigned_to_user_id === $user->id;
    }

    private function resolveUserAssignment(Task $task, User $user): TaskAssignment
    {
        $assignment = $task->assignments()
            ->where('assigned_to_user_id', $user->id)
            ->orderByDesc('id')
            ->first();

        if (! $assignment) {
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
}
