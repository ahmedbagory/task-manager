<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WhatsAppMediaService
{
    private const MAX_FILE_SIZE_BYTES = 20 * 1024 * 1024;

    /**
     * @var array<string, list<string>>
     */
    private const MIME_TYPES_BY_CATEGORY = [
        'image' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ],
        'document' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
        'audio' => [
            'audio/mpeg',
            'audio/ogg',
            'audio/webm',
            'audio/mp4',
        ],
        'video' => [
            'video/mp4',
            'video/webm',
        ],
    ];

    /**
     * @var array<string, string>
     */
    private const DIRECTORIES = [
        'image' => 'images',
        'document' => 'documents',
        'audio' => 'audio',
        'video' => 'videos',
        'sticker' => 'stickers',
    ];

    /**
     * @return list<string>
     */
    public static function allowedMimeTypes(): array
    {
        return array_values(array_unique(array_merge(
            ...array_values(self::MIME_TYPES_BY_CATEGORY),
        )));
    }

    public static function maxFileSizeBytes(): int
    {
        return self::MAX_FILE_SIZE_BYTES;
    }

    /**
     * @return array{
     *   type:string,
     *   mime_type:string,
     *   path:string,
     *   storage_path:string,
     *   url:string,
     *   original_name:string,
     *   size:int
     * }
     */
    public function storeOutgoingUpload(UploadedFile $file): array
    {
        $mimeType = (string) ($file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream');
        $type = $this->resolveTypeFromMime($mimeType);

        if ($type === null) {
            throw new \InvalidArgumentException('Unsupported WhatsApp media type.');
        }

        $relativePath = sprintf(
            'whatsapp-media/outgoing/%s/%s',
            $this->directoryForType($type),
            $this->generateSafeFilename($file->getClientOriginalName(), $mimeType),
        );

        $storedPath = Storage::disk('public')->putFileAs(
            dirname($relativePath),
            $file,
            basename($relativePath),
        );

        return [
            'type' => $type,
            'mime_type' => $mimeType,
            'path' => $storedPath,
            'storage_path' => storage_path('app/public/'.$storedPath),
            'url' => Storage::disk('public')->url($storedPath),
            'original_name' => (string) $file->getClientOriginalName(),
            'size' => (int) ($file->getSize() ?: 0),
        ];
    }

    public function resolveTypeFromMime(?string $mimeType, ?string $fallbackType = null): ?string
    {
        $mimeType = trim((string) $mimeType);
        $fallbackType = $this->normalizeType($fallbackType);

        if ($fallbackType === 'sticker') {
            return 'sticker';
        }

        foreach (self::MIME_TYPES_BY_CATEGORY as $type => $mimeTypes) {
            if (in_array($mimeType, $mimeTypes, true)) {
                return $type;
            }
        }

        if ($fallbackType === 'image' && $mimeType === 'image/webp') {
            return 'sticker';
        }

        return $fallbackType;
    }

    public function relativePublicPathFromStoragePath(?string $storagePath): ?string
    {
        $storagePath = str_replace('\\', '/', trim((string) $storagePath));

        if ($storagePath === '') {
            return null;
        }

        $prefix = str_replace('\\', '/', storage_path('app/public/')).'';

        if (Str::startsWith($storagePath, $prefix)) {
            return ltrim(Str::after($storagePath, $prefix), '/');
        }

        if (Str::startsWith($storagePath, 'storage/app/public/')) {
            return ltrim(Str::after($storagePath, 'storage/app/public/'), '/');
        }

        return ltrim($storagePath, '/');
    }

    public function publicUrlForRelativePath(?string $relativePath): ?string
    {
        $relativePath = ltrim(trim((string) $relativePath), '/');

        if ($relativePath === '') {
            return null;
        }

        return Storage::disk('public')->url($relativePath);
    }

    public function humanReadableSize(?int $size): ?string
    {
        if (! is_int($size) || $size <= 0) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $value = (float) $size;
        $unitIndex = 0;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        $precision = $unitIndex === 0 ? 0 : 1;

        return number_format($value, $precision).' '.$units[$unitIndex];
    }

    private function directoryForType(string $type): string
    {
        return self::DIRECTORIES[$type] ?? 'documents';
    }

    private function normalizeType(?string $type): ?string
    {
        $type = strtolower(trim((string) $type));

        return $type === '' ? null : $type;
    }

    private function generateSafeFilename(?string $originalName, string $mimeType): string
    {
        $timestamp = now()->format('Ymd_His');
        $random = Str::lower(Str::random(8));
        $extension = strtolower(pathinfo((string) $originalName, PATHINFO_EXTENSION));

        if ($extension === '') {
            $extension = strtolower((string) Str::of($mimeType)->after('/'));
            $extension = match ($extension) {
                'jpeg' => 'jpg',
                'quicktime' => 'mov',
                'msword' => 'doc',
                'vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'vnd.ms-excel' => 'xls',
                'vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                default => preg_replace('/[^a-z0-9]+/', '', $extension) ?: 'bin',
            };
        }

        return "wa_{$timestamp}_{$random}.{$extension}";
    }
}
