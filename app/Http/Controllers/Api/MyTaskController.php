<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MyTasks\IndexMyTasksRequest;
use App\Http\Requests\Api\MyTasks\RejectTaskRequest;
use App\Http\Requests\Api\MyTasks\StoreTaskAttachmentRequest;
use App\Http\Requests\Api\MyTasks\StoreTaskCommentRequest;
use App\Http\Requests\Api\MyTasks\UpdateTaskStatusRequest;
use App\Http\Resources\Api\TaskAttachmentResource;
use App\Http\Resources\Api\TaskCommentResource;
use App\Http\Resources\Api\TaskDetailResource;
use App\Http\Resources\Api\TaskListResource;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Services\Tasks\TaskAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MyTaskController extends Controller
{
    use RespondsWithJson;

    public function index(IndexMyTasksRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->cannot('viewAny', Task::class)) {
            return $this->errorResponse(message: 'Forbidden.', status: 403);
        }

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);

        $tasksQuery = Task::query()
            ->where('assigned_to_user_id', $user->id)
            ->with([
                'department.parent',
                'category',
                'latestAssignment.assignedByUser',
                'reportedByUser',
                'createdByUser',
            ])
            ->withCount('comments')
            ->orderByDesc('updated_at');

        if (! empty($validated['status'])) {
            $tasksQuery->where('status', (string) $validated['status']);
        }

        $tasks = $tasksQuery->paginate($perPage)->withQueryString();
        $taskItems = TaskListResource::collection(collect($tasks->items()))->resolve();

        return $this->successResponse(
            data: ['tasks' => $taskItems],
            message: 'Tasks fetched successfully.',
            meta: ['pagination' => $this->paginationMeta($tasks)],
        );
    }

    public function show(Request $request, int $task): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $taskModel = $this->resolveAssignedTask($user, $task);

        if (! $taskModel) {
            return $this->errorResponse(message: 'Task not found.', status: 404);
        }

        if ($user->cannot('viewAssignedWorkspace', $taskModel)) {
            return $this->errorResponse(message: 'Forbidden.', status: 403);
        }

        return $this->successResponse(
            data: ['task' => (new TaskDetailResource($taskModel))->resolve()],
            message: 'Task fetched successfully.',
        );
    }

    public function accept(Request $request, int $task, TaskAssignmentService $taskAssignmentService): JsonResponse
    {
        return $this->handleAssignmentTransition(
            request: $request,
            taskId: $task,
            ability: 'respondToAssignment',
            handler: function (TaskAssignment $assignment, User $user) use ($taskAssignmentService): void {
                $taskAssignmentService->acceptAssignment($assignment, $user);
            },
            successMessage: 'Task accepted successfully.',
        );
    }

    public function start(Request $request, int $task, TaskAssignmentService $taskAssignmentService): JsonResponse
    {
        return $this->handleAssignmentTransition(
            request: $request,
            taskId: $task,
            ability: 'respondToAssignment',
            handler: function (TaskAssignment $assignment, User $user) use ($taskAssignmentService): void {
                $taskAssignmentService->startTask($assignment, $user);
            },
            successMessage: 'Work started successfully.',
        );
    }

    public function complete(Request $request, int $task, TaskAssignmentService $taskAssignmentService): JsonResponse
    {
        return $this->handleAssignmentTransition(
            request: $request,
            taskId: $task,
            ability: 'respondToAssignment',
            handler: function (TaskAssignment $assignment, User $user) use ($taskAssignmentService): void {
                $taskAssignmentService->completeAssignedTask($assignment, $user);
            },
            successMessage: 'Task completed successfully.',
        );
    }

    public function waitResponse(Request $request, int $task, TaskAssignmentService $taskAssignmentService): JsonResponse
    {
        return $this->handleAssignmentTransition(
            request: $request,
            taskId: $task,
            ability: 'respondToAssignment',
            handler: function (TaskAssignment $assignment, User $user) use ($taskAssignmentService): void {
                $taskAssignmentService->waitResponseTask($assignment, $user);
            },
            successMessage: 'Task moved to waiting response successfully.',
        );
    }

    public function resume(Request $request, int $task, TaskAssignmentService $taskAssignmentService): JsonResponse
    {
        return $this->handleAssignmentTransition(
            request: $request,
            taskId: $task,
            ability: 'respondToAssignment',
            handler: function (TaskAssignment $assignment, User $user) use ($taskAssignmentService): void {
                $taskAssignmentService->resumeTask($assignment, $user);
            },
            successMessage: 'Task resumed successfully.',
        );
    }

    public function updateStatus(
        UpdateTaskStatusRequest $request,
        int $task,
        TaskAssignmentService $taskAssignmentService
    ): JsonResponse {
        $status = (string) $request->validated('status');

        return match ($status) {
            'wait_response', 'waiting_response' => $this->handleAssignmentTransition(
                request: $request,
                taskId: $task,
                ability: 'respondToAssignment',
                handler: function (TaskAssignment $assignment, User $user) use ($taskAssignmentService): void {
                    $taskAssignmentService->waitResponseTask($assignment, $user);
                },
                successMessage: 'Task moved to waiting response successfully.',
            ),
            'in_progress' => $this->handleAssignmentTransition(
                request: $request,
                taskId: $task,
                ability: 'respondToAssignment',
                handler: function (TaskAssignment $assignment, User $user) use ($taskAssignmentService): void {
                    $taskAssignmentService->resumeTask($assignment, $user);
                },
                successMessage: 'Task resumed successfully.',
            ),
        };
    }

    public function reject(
        RejectTaskRequest $request,
        int $task,
        TaskAssignmentService $taskAssignmentService
    ): JsonResponse {
        $reason = (string) $request->validated('reason');

        return $this->handleAssignmentTransition(
            request: $request,
            taskId: $task,
            ability: 'respondToAssignment',
            handler: function (TaskAssignment $assignment, User $user) use ($taskAssignmentService, $reason): void {
                $taskAssignmentService->rejectAssignment($assignment, $user, $reason);
            },
            successMessage: 'Task rejected successfully.',
        );
    }

    public function comment(StoreTaskCommentRequest $request, int $task): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $taskModel = $this->resolveAssignedTask($user, $task);

        if (! $taskModel) {
            return $this->errorResponse(message: 'Task not found.', status: 404);
        }

        if ($user->cannot('addWorkspaceComment', $taskModel)) {
            return $this->errorResponse(message: 'Forbidden.', status: 403);
        }

        $comment = $taskModel->comments()->create([
            'user_id' => $user->id,
            'comment' => (string) $request->validated('comment'),
            'is_internal' => false,
        ]);
        $comment->load('user');

        return $this->successResponse(
            data: ['comment' => (new TaskCommentResource($comment))->resolve()],
            message: 'Comment added successfully.',
            status: 201,
        );
    }

    public function attachment(StoreTaskAttachmentRequest $request, int $task): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $taskModel = $this->resolveAssignedTask($user, $task);

        if (! $taskModel) {
            return $this->errorResponse(message: 'Task not found.', status: 404);
        }

        if ($user->cannot('uploadWorkspaceAttachment', $taskModel)) {
            return $this->errorResponse(message: 'Forbidden.', status: 403);
        }

        $file = $request->file('attachment');
        $storedPath = $file->store("task-attachments/{$taskModel->id}", 'local');

        $attachment = $taskModel->attachments()->create([
            'user_id' => $user->id,
            'disk' => 'local',
            'path' => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'type' => str_starts_with((string) $file->getMimeType(), 'image/') ? 'image' : 'file',
        ]);
        $attachment->load('user');

        return $this->successResponse(
            data: ['attachment' => (new TaskAttachmentResource($attachment))->resolve()],
            message: 'Attachment uploaded successfully.',
            status: 201,
        );
    }

    public function downloadAttachment(Request $request, int $task, int $attachment): StreamedResponse|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $taskModel = $this->resolveAssignedTask($user, $task);

        if (! $taskModel) {
            return $this->errorResponse(message: 'Task not found.', status: 404);
        }

        $attachmentModel = $taskModel->attachments()->whereKey($attachment)->first();

        if (! $attachmentModel) {
            return $this->errorResponse(message: 'Attachment not found.', status: 404);
        }

        $disk = Storage::disk($attachmentModel->disk);

        if (! $disk->exists($attachmentModel->path)) {
            return $this->errorResponse(message: 'File not found on storage.', status: 404);
        }

        return $disk->download(
            $attachmentModel->path,
            $attachmentModel->original_name ?: basename($attachmentModel->path),
        );
    }

    /**
     * @param  callable(TaskAssignment, User): void  $handler
     */
    private function handleAssignmentTransition(
        Request $request,
        int $taskId,
        string $ability,
        callable $handler,
        string $successMessage
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $task = $this->resolveAssignedTask($user, $taskId, ['department.parent', 'category']);

        if (! $task) {
            return $this->errorResponse(message: 'Task not found.', status: 404);
        }

        if ($user->cannot($ability, $task)) {
            return $this->errorResponse(message: 'Forbidden.', status: 403);
        }

        $assignment = $this->resolveUserAssignment($task, $user);

        if (! $assignment) {
            return $this->errorResponse(
                message: 'No active assignment found for this task.',
                status: 422,
                errors: ['assignment' => ['No assignment is available for this user.']],
            );
        }

        try {
            $handler($assignment, $user);
        } catch (ValidationException $exception) {
            return $this->errorResponse(
                message: 'Validation failed.',
                status: 422,
                errors: $exception->errors(),
            );
        }

        $task->refresh()->load([
            'department',
            'department.parent',
            'category',
            'assignedToUser',
            'latestAssignment.assignedByUser',
            'reportedByUser',
            'createdByUser',
            'assignments.assignedByUser',
            'assignments.assignedToUser',
            'comments.user',
            'attachments.user',
        ]);

        return $this->successResponse(
            data: ['task' => (new TaskDetailResource($task))->resolve()],
            message: $successMessage,
        );
    }

    /**
     * @param  array<int, string>  $with
     */
    private function resolveAssignedTask(User $user, int $taskId, array $with = []): ?Task
    {
        return Task::query()
            ->whereKey($taskId)
            ->where('assigned_to_user_id', $user->id)
            ->with(array_merge([
                'department',
                'department.parent',
                'category',
                'assignedToUser',
                'latestAssignment.assignedByUser',
                'reportedByUser',
                'createdByUser',
                'assignments.assignedByUser',
                'assignments.assignedToUser',
                'comments.user',
                'attachments.user',
            ], $with))
            ->first();
    }

    private function resolveUserAssignment(Task $task, User $user): ?TaskAssignment
    {
        return $task->assignments()
            ->where('assigned_to_user_id', $user->id)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, int>
     */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
