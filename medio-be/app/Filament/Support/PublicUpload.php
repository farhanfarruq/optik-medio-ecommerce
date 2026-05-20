<?php

namespace App\Filament\Support;

use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicUpload
{
    public static function image(FileUpload $component, string $directory, string $previewHeight = '160px'): FileUpload
    {
        return self::file($component, $directory)
            ->image()
            ->previewable()
            ->imagePreviewHeight($previewHeight);
    }

    public static function file(FileUpload $component, string $directory): FileUpload
    {
        return $component
            ->disk('public')
            ->visibility('public')
            ->directory($directory)
            ->fetchFileInformation(false)
            ->deletable()
            ->openable()
            ->downloadable()
            ->getUploadedFileUsing(fn (string $file): ?array => self::uploadedFileInfo($file))
            ->deleteUploadedFileUsing(function (string $file): void {
                self::deletePublicFile($file);
            })
            ->dehydrateStateUsing(fn ($state) => self::normalizeState($state));
    }

    public static function url(?string $file): ?string
    {
        if (blank($file)) {
            return null;
        }

        if (Str::startsWith($file, ['http://', 'https://'])) {
            return $file;
        }

        $path = self::normalizePath($file);

        return $path ? '/storage/' . ltrim($path, '/') : $file;
    }

    private static function uploadedFileInfo(string $file): ?array
    {
        if (blank($file)) {
            return null;
        }

        $path = self::normalizePath($file);
        $url = self::url($file);
        $nameSource = parse_url($file, PHP_URL_PATH) ?: $file;

        return [
            'name' => basename($nameSource),
            'size' => ($path && Storage::disk('public')->exists($path)) ? max(1, Storage::disk('public')->size($path)) : 1,
            'type' => self::mimeType($nameSource),
            'url' => $url,
        ];
    }

    private static function deletePublicFile(string $file): void
    {
        $path = self::normalizePath($file);

        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private static function normalizeState($state)
    {
        if (blank($state)) {
            return $state;
        }

        if (is_array($state)) {
            return collect($state)
                ->map(fn ($file) => is_string($file) ? self::normalizePath($file) : $file)
                ->filter()
                ->values()
                ->all();
        }

        return is_string($state) ? self::normalizePath($state) : $state;
    }

    private static function normalizePath(?string $file): ?string
    {
        if (blank($file)) {
            return null;
        }

        $path = parse_url($file, PHP_URL_PATH) ?: $file;
        $path = Str::replace('\\', '/', $path);
        $path = ltrim($path, '/');

        if (Str::startsWith($path, 'storage/')) {
            return Str::after($path, 'storage/');
        }

        if (str_contains($path, '/storage/')) {
            return Str::after($path, '/storage/');
        }

        return $path;
    }

    private static function mimeType(string $file): string
    {
        return match (Str::lower(pathinfo(parse_url($file, PHP_URL_PATH) ?: $file, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }
}
