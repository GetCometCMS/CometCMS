<?php

declare(strict_types=1);

namespace CometCMS\Site;

use CometCMS\Core\MimeDetector;
use CometCMS\Core\Security;
use CometCMS\Core\ValidationException;
use CometCMS\Media\MediaRepository;
use CometCMS\Workspaces\WorkspaceContext;

/** Validates genuine public form uploads and tracks files for rollback. */
final class FormUploads
{
    private const SAFE_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif',
        'mp4', 'webm', 'mov', 'm4v', 'avi', 'mkv', 'mpeg', 'mpg', 'ogv', '3gp', '3g2',
        'pdf', 'txt', 'csv', 'md', 'rtf', 'doc', 'docx', 'odt', 'xls', 'xlsx', 'ods', 'ppt', 'pptx', 'odp',
        'zip', '7z', 'rar', 'gz', 'tar', 'mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac',
    ];

    private array $stored = [];
    private ?MediaRepository $media = null;

    public function __construct(private readonly WorkspaceContext $workspace) {}

    public static function maxBytes(): int
    {
        return max(1, min(
            (int) comet_config('site.forms.max_upload_bytes', 10 * 1024 * 1024),
            (int) comet_config('media.max_upload_bytes', 1073741824),
        ));
    }

    /** @return array<string, list<string>> filename arrays for declared media fields only */
    public function store(array $inputs, array $files): array
    {
        $pending = [];
        $errors = [];
        $total = 0;
        $allowed = comet_config('media.allowed_mime_types', []);
        foreach ($inputs as $field) {
            if (($field['config']['type'] ?? '') !== 'media') {
                continue;
            }
            $key = $field['key'];
            $pending[$key] = [];
            try {
                foreach ($this->filesFor($files, $key) as $file) {
                    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    if ($file['error'] !== UPLOAD_ERR_OK) {
                        throw new \RuntimeException(in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                            ? 'File exceeds the upload size limit.' : 'Upload failed. Please select the file again.');
                    }
                    if (!is_uploaded_file($file['tmp_name'])) {
                        throw new \RuntimeException('Invalid uploaded file.');
                    }
                    $size = filesize($file['tmp_name']);
                    if ($size === false || $size <= 0 || $size > self::maxBytes()) {
                        throw new \RuntimeException('File is empty or exceeds the upload size limit.');
                    }
                    $total += $size;
                    if ($total > self::maxBytes()) {
                        throw new \RuntimeException('Attachments exceed the total upload size limit.');
                    }
                    $mime = MimeDetector::detect($file['tmp_name'], $file['name']);
                    // Never retain an executable extension, even when its contents look like plain text.
                    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if (!in_array($mime, is_array($allowed) ? $allowed : [], true)
                        || !in_array($extension, self::SAFE_EXTENSIONS, true)) {
                        throw new \RuntimeException('File type is not allowed.');
                    }
                    $pending[$key][] = $file;
                }
                if (empty($field['config']['multiple']) && count($pending[$key]) > 1) {
                    throw new \RuntimeException('Only one file is allowed.');
                }
                if (!empty($field['config']['required']) && $pending[$key] === []) {
                    throw new \RuntimeException('This field is required.');
                }
            } catch (\RuntimeException $e) {
                $errors[$key] = $e->getMessage();
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $values = [];
        foreach ($pending as $key => $uploads) {
            $values[$key] = [];
            foreach ($uploads as $file) {
                $this->media ??= new MediaRepository($this->workspace);
                $base = Security::slug(pathinfo($file['name'], PATHINFO_FILENAME)) ?: 'upload';
                $name = substr($base, 0, 80) . '-' . bin2hex(random_bytes(16)) . '.' . strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!move_uploaded_file($file['tmp_name'], $this->media->directory() . '/' . $name)) {
                    throw new ValidationException([$key => 'Could not store uploaded file.']);
                }
                $this->stored[] = $name;
                $this->media->updateVisibility($name, 'private');
                $values[$key][] = $name;
            }
        }
        return $values;
    }

    public function rollback(): void
    {
        foreach ($this->stored as $name) {
            $this->media?->delete($name);
        }
        $this->stored = [];
    }

    /** Unzip PHP's nested $_FILES structure without accepting nested or mismatched shapes. */
    private function filesFor(array $files, string $key): array
    {
        if (!array_key_exists('fields', $files)) {
            return [];
        }
        $source = $files['fields'];
        $parts = ['name', 'tmp_name', 'error', 'size', 'type'];
        foreach ($parts as $part) {
            if (!is_array($source) || !is_array($source[$part] ?? null)) {
                throw new \RuntimeException('Invalid upload data.');
            }
        }
        $present = array_map(static fn(string $part): bool => array_key_exists($key, $source[$part]), $parts);
        if (!in_array(true, $present, true)) {
            return [];
        }
        if (in_array(false, $present, true)) {
            throw new \RuntimeException('Invalid upload data.');
        }
        $names = $source['name'][$key];
        $indexes = is_array($names) ? array_keys($names) : [null];
        foreach ($parts as $part) {
            $value = $source[$part][$key];
            if (is_array($names) ? !is_array($value) || array_keys($value) !== $indexes : is_array($value)) {
                throw new \RuntimeException('Invalid upload data.');
            }
        }
        $result = [];
        foreach ($indexes as $index) {
            $file = [];
            foreach ($parts as $part) {
                $file[$part] = $index === null ? $source[$part][$key] : $source[$part][$key][$index];
            }
            if (!is_string($file['name']) || !is_string($file['tmp_name']) || !is_string($file['type']) || !is_int($file['error']) || !is_int($file['size'])) {
                throw new \RuntimeException('Invalid upload data.');
            }
            $result[] = $file;
        }
        return $result;
    }
}
