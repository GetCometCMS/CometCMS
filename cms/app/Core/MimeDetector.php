<?php

declare(strict_types=1);

namespace CometCMS\Core;

final class MimeDetector
{
    private const EXTENSION_MAP = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'svg'  => 'image/svg+xml',
        'avif' => 'image/avif',
        'mp4'  => 'video/mp4',
        'webm' => 'video/webm',
        'mov'  => 'video/quicktime',
        'm4v'  => 'video/x-m4v',
        'avi'  => 'video/x-msvideo',
        'mkv'  => 'video/x-matroska',
        'mpeg' => 'video/mpeg',
        'mpg'  => 'video/mpeg',
        'ogv'  => 'video/ogg',
        '3gp'  => 'video/3gpp',
        '3g2'  => 'video/3gpp2',
        'pdf'  => 'application/pdf',
        'txt'  => 'text/plain',
        'csv'  => 'text/csv',
        'md'   => 'text/markdown',
        'rtf'  => 'application/rtf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'odt'  => 'application/vnd.oasis.opendocument.text',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'odp'  => 'application/vnd.oasis.opendocument.presentation',
        'zip'  => 'application/zip',
        '7z'   => 'application/x-7z-compressed',
        'rar'  => 'application/x-rar-compressed',
        'gz'   => 'application/gzip',
        'tar'  => 'application/x-tar',
        'mp3'  => 'audio/mpeg',
        'wav'  => 'audio/wav',
        'ogg'  => 'audio/ogg',
        'm4a'  => 'audio/x-m4a',
        'aac'  => 'audio/aac',
        'flac' => 'audio/flac',
    ];

    /** Return an allow-listed extension for a detected MIME/name pair. */
    public static function validatedExtension(string $mime, string $name): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $expected = self::EXTENSION_MAP[$extension] ?? null;
        $mime = match ($mime) {
            'application/x-zip-compressed' => 'application/zip',
            'application/vnd.rar' => 'application/x-rar-compressed',
            'audio/x-wav' => 'audio/wav',
            'audio/mp4' => 'audio/x-m4a',
            'audio/x-flac' => 'audio/flac',
            'text/rtf' => 'application/rtf',
            default => $mime,
        };
        // Text formats and ZIP-based office documents can have generic MIME
        // detections. Every accepted suffix still comes from the safe map.
        $compatible = $expected !== null && ($mime === $expected
            || ($mime === 'text/plain' && in_array($extension, ['txt', 'csv', 'md'], true))
            || ($mime === 'application/zip' && in_array($extension, ['docx', 'xlsx', 'pptx', 'odt', 'ods', 'odp'], true)));
        if (!$compatible) throw new \InvalidArgumentException('File extension does not match an allowed file type.');
        return $extension === 'jpeg' ? 'jpg' : $extension;
    }

    public static function assertRename(string $current, string $name): void
    {
        $extension = strtolower(pathinfo($current, PATHINFO_EXTENSION));
        // Some hosts select handlers from any suffix, e.g. file.php.gif.
        if (str_contains(pathinfo($name, PATHINFO_FILENAME), '.')
            || !isset(self::EXTENSION_MAP[$extension]) || strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== $extension) {
            throw new \InvalidArgumentException('Renaming cannot change the file type.');
        }
    }

    public static function detect(string $path, ?string $originalName = null): string
    {
        if (class_exists(\finfo::class)) {
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }

        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($path);

            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }

        $extension = strtolower(pathinfo($originalName ?: $path, PATHINFO_EXTENSION));

        return self::EXTENSION_MAP[$extension] ?? 'application/octet-stream';
    }
}
