<?php

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\WhatsApp\WhatsAppMediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TaskAttachmentService
{
    public function __construct(
        private readonly WhatsAppMediaService $whatsAppMediaService,
    ) {}

    /**
     * @param  iterable<int, UploadedFile>  $files
     * @return Collection<int, TaskAttachment>
     */
    public function storeUploadedAttachments(Task $task, iterable $files, ?User $actor = null): Collection
    {
        return collect($files)
            ->filter(fn ($file): bool => $file instanceof UploadedFile)
            ->map(fn (UploadedFile $file): TaskAttachment => $this->storeUploadedAttachment($task, $file, $actor))
            ->values();
    }

    public function storeUploadedAttachment(Task $task, UploadedFile $file, ?User $actor = null): TaskAttachment
    {
        $storedPath = $file->store("task-attachments/{$task->id}", 'local');

        return $task->attachments()->create([
            'user_id' => $actor?->id,
            'disk' => 'local',
            'path' => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'type' => TaskAttachment::resolveType($file->getMimeType()),
        ]);
    }

    /**
     * @param  array<int, int|string>|Collection<int, int|string>  $attachmentIds
     */
    public function removeTaskAttachments(Task $task, array|Collection $attachmentIds): void
    {
        $ids = collect($attachmentIds)
            ->filter(fn ($value): bool => filled($value))
            ->map(fn ($value): int => (int) $value)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $task->attachments()
            ->whereIn('id', $ids->all())
            ->get()
            ->each(function (TaskAttachment $attachment): void {
                $disk = Storage::disk($attachment->disk);

                if ($disk->exists($attachment->path)) {
                    $disk->delete($attachment->path);
                }

                $attachment->delete();
            });
    }

    public function copyFromWhatsappMessage(Task $task, WhatsappMessage $message, ?User $actor = null): ?TaskAttachment
    {
        if (! $message->hasMedia()) {
            return null;
        }

        $originalName = $this->resolveOriginalName($message);

        $existing = $task->attachments()
            ->where('original_name', $originalName)
            ->where('mime_type', $message->media_mime)
            ->where('size', $message->media_size)
            ->first();

        if ($existing) {
            return $existing;
        }

        $storedPath = "task-attachments/{$task->id}/".$this->buildWhatsappFilename($message, $originalName);
        $disk = Storage::disk('local');

        $copied = false;

        if ($sourceRelativePath = $this->resolveLocalWhatsappMediaRelativePath($message)) {
            $publicDisk = Storage::disk('public');

            if ($publicDisk->exists($sourceRelativePath)) {
                $stream = $publicDisk->readStream($sourceRelativePath);

                if (is_resource($stream)) {
                    $disk->writeStream($storedPath, $stream);
                    fclose($stream);
                    $copied = true;
                }
            }
        }

        if (! $copied && filled($message->media_url)) {
            $response = Http::timeout(20)->connectTimeout(10)->get((string) $message->media_url);

            if ($response->successful()) {
                $disk->put($storedPath, $response->body());
                $copied = true;
            }
        }

        if (! $copied) {
            return null;
        }

        return $task->attachments()->create([
            'user_id' => $actor?->id,
            'disk' => 'local',
            'path' => $storedPath,
            'original_name' => $originalName,
            'mime_type' => $message->media_mime,
            'size' => $message->media_size,
            'type' => TaskAttachment::resolveType($message->media_mime),
        ]);
    }

    private function resolveOriginalName(WhatsappMessage $message): string
    {
        $originalName = trim((string) $message->media_name);

        if ($originalName !== '') {
            return $originalName;
        }

        $extension = Str::lower(pathinfo((string) $message->media_path, PATHINFO_EXTENSION));

        if ($extension === '' && filled($message->media_mime)) {
            $extension = match ((string) Str::of((string) $message->media_mime)->after('/')) {
                'jpeg' => 'jpg',
                'plain' => 'txt',
                default => preg_replace('/[^a-z0-9]+/', '', (string) Str::of((string) $message->media_mime)->after('/')) ?: 'bin',
            };
        }

        return 'whatsapp-message-'.($message->id ?: 'file').($extension !== '' ? '.'.$extension : '');
    }

    private function buildWhatsappFilename(WhatsappMessage $message, string $originalName): string
    {
        $extension = Str::lower(pathinfo($originalName, PATHINFO_EXTENSION));
        $suffix = $extension !== '' ? '.'.$extension : '';

        return 'whatsapp-message-'.$message->id.'-'.Str::random(8).$suffix;
    }

    private function resolveLocalWhatsappMediaRelativePath(WhatsappMessage $message): ?string
    {
        $relativePath = $this->whatsAppMediaService->relativePublicPathFromStoragePath($message->media_path);

        if (filled($relativePath)) {
            return $relativePath;
        }

        $mediaPath = str_replace('\\', '/', trim((string) $message->media_path));

        if ($mediaPath !== '') {
            $candidates = [
                ltrim(Str::after($mediaPath, 'storage/app/public/'), '/'),
                ltrim(Str::after($mediaPath, 'public/storage/'), '/'),
                ltrim(Str::after($mediaPath, 'storage/'), '/'),
                ltrim(Str::after($mediaPath, 'public/'), '/'),
            ];

            foreach ($candidates as $candidate) {
                if ($candidate === '') {
                    continue;
                }

                if (Storage::disk('public')->exists($candidate)) {
                    return $candidate;
                }
            }
        }

        $mediaUrl = trim((string) $message->media_url);

        if ($mediaUrl === '') {
            return null;
        }

        $path = parse_url($mediaUrl, PHP_URL_PATH);

        if (! is_string($path) || ! str_contains($path, '/storage/')) {
            return null;
        }

        $relativePath = ltrim(Str::after($path, '/storage/'), '/');

        return Storage::disk('public')->exists($relativePath)
            ? $relativePath
            : null;
    }
}
