<?php

declare(strict_types=1);

namespace CometCMS\Media;

use CometCMS\Core\MimeDetector;
use CometCMS\Workspaces\WorkspaceContext;

final class MediaRepository
{
    private string $mediaDir;
    private string $thumbDir;
    private string $variantDir;
    private string $metadataPath;
    private WorkspaceContext $workspace;

    public function __construct(?WorkspaceContext $workspace = null)
    {
        $this->workspace = $workspace ?? WorkspaceContext::active();
        WorkspaceContext::setActive($this->workspace->slug());
        $this->workspace->ensure();
        $this->mediaDir = $this->workspace->path('media');
        $this->thumbDir = $this->workspace->path('media-thumbs');
        $this->variantDir = $this->workspace->path('media-variants');
        $this->metadataPath = $this->workspace->path('media-meta') . '/index.json';

        if (!is_dir($this->thumbDir)) {
            mkdir($this->thumbDir, 0775, true);
        }

        if (!is_dir($this->variantDir)) {
            mkdir($this->variantDir, 0775, true);
        }

        if (!is_dir(dirname($this->metadataPath))) {
            mkdir(dirname($this->metadataPath), 0775, true);
        }
    }

    public function directory(): string
    {
        return $this->mediaDir;
    }

    public function filePath(string $file): string
    {
        return $this->path($file);
    }

    public function files(string $query = '', ?string $category = null, string $type = 'all', string $sort = 'newest', ?string $visibility = null): array
    {
        $metadata = $this->metadata();
        $files = $this->matchingFiles($query, $category, $type, $sort, $metadata, $visibility);

        return array_map(fn(string $file): array => $this->item($file, $metadata), $files);
    }

    public function limitedFiles(string $query = '', ?string $category = null, ?int $limit = null, int $offset = 0, string $type = 'all', string $sort = 'newest', ?string $visibility = null, ?array $usedFiles = null): array
    {
        $metadata = $this->metadata();
        $files = $this->matchingFiles($query, $category, $type, $sort, $metadata, $visibility);

        if ($usedFiles !== null) {
            $files = array_values(array_filter($files, static fn(string $file): bool => !isset($usedFiles[$file])));
        }
        $total = count($files);
        $offset = max(0, $offset);
        $limit = $limit === null ? max(0, $total - $offset) : max(1, $limit);
        $limitedFiles = array_slice($files, $offset, $limit);

        return [
            'data' => array_map(fn(string $file): array => $this->item($file, $metadata), $limitedFiles),
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
            ],
        ];
    }

    public function stats(): array
    {
        $metadata = $this->metadata();
        $files = $this->matchingFiles('', null, 'all', 'name', $metadata);
        $categoryCounts = [];
        $uncategorized = 0;

        foreach ($files as $file) {
            $category = $this->categoryFor($file, $metadata);

            if ($category === '') {
                $uncategorized++;
            }

            foreach ($metadata['categories'] as $categoryPath) {
                if ($this->categoryMatches($category, (string) $categoryPath)) {
                    $categoryCounts[$categoryPath] = ($categoryCounts[$categoryPath] ?? 0) + 1;
                }
            }
        }

        return [
            'total' => count($files),
            'uncategorized' => $uncategorized,
            'categories' => $categoryCounts,
        ];
    }

    private function matchingFiles(string $query = '', ?string $category = null, string $type = 'all', string $sort = 'newest', ?array $metadata = null, ?string $visibility = null): array
    {
        $files = array_values(array_filter(
            scandir($this->mediaDir) ?: [],
            fn(string $file): bool => $file[0] !== '.' && is_file($this->path($file))
        ));

        $query = trim(strtolower($query));
        $metadata ??= $this->metadata();

        $files = array_values(array_filter($files, function (string $file) use ($query, $category, $metadata, $type, $visibility): bool {
            if ($query !== '' && !str_contains(strtolower($file), $query)) {
                return false;
            }

            if ($category !== null && !$this->categoryMatches($this->categoryFor($file, $metadata), $category)) {
                return false;
            }

            if (!$this->matchesType($file, $type)) {
                return false;
            }

            if ($visibility !== null) {
                $fileVisibility = (string) ($metadata['files'][$file]['visibility'] ?? 'public');
                if ($fileVisibility !== $visibility) {
                    return false;
                }
            }

            return true;
        }));

        $this->sortFiles($files, $sort, $metadata);

        return $files;
    }

    private function sortFiles(array &$files, string $sort, array $metadata): void
    {
        usort($files, function (string $a, string $b) use ($sort, $metadata): int {
            return match ($sort) {
                'oldest' => $this->uploadedTimestamp($a, $metadata) <=> $this->uploadedTimestamp($b, $metadata),
                'name' => strcasecmp($a, $b),
                'size' => ((int) filesize($this->path($b))) <=> ((int) filesize($this->path($a))),
                default => $this->uploadedTimestamp($b, $metadata) <=> $this->uploadedTimestamp($a, $metadata),
            };
        });
    }

    private function uploadedTimestamp(string $file, array $metadata): int
    {
        $uploadedAt = (string) ($metadata['files'][$file]['uploaded_at'] ?? '');
        $time = $uploadedAt !== '' ? strtotime($uploadedAt) : false;

        if ($time !== false) {
            return $time;
        }

        $modifiedAt = filemtime($this->path($file));

        return $modifiedAt === false ? 0 : $modifiedAt;
    }

    private function matchesType(string $file, string $type): bool
    {
        return MediaFileType::matches($file, $type);
    }

    public function categories(): array
    {
        return $this->metadata()['categories'];
    }

    public function addCategory(string $name, string $parent = ''): array
    {
        $name = $this->normalizeCategory($parent === '' ? $name : $parent . ' / ' . $name);

        if ($name === '') {
            throw new \InvalidArgumentException('Category name cannot be empty.');
        }

        $metadata = $this->metadata();

        $changed = false;
        foreach ($this->categoryPathsFor($name) as $category) {
            if (!in_array($category, $metadata['categories'], true)) {
                $metadata['categories'][] = $category;
                $changed = true;
            }
        }

        if ($changed) {
            $metadata['categories'] = $this->sortCategories($metadata['categories']);
            $this->writeMetadata($metadata);
        }

        return $metadata['categories'];
    }

    public function renameCategory(string $oldName, string $newName): array
    {
        $oldName = $this->normalizeCategory($oldName);
        $newName = $this->normalizeCategory($newName);

        if ($oldName === '' || $newName === '') {
            throw new \InvalidArgumentException('Category name cannot be empty.');
        }

        $metadata = $this->metadata();

        if (!in_array($oldName, $metadata['categories'], true)) {
            throw new \InvalidArgumentException('Category not found.');
        }

        $renamedCategories = [];
        foreach ($metadata['categories'] as $category) {
            $renamedCategories[] = $this->renameCategoryPath($category, $oldName, $newName);
        }

        foreach ($metadata['files'] as $file => $item) {
            $category = (string) ($item['category'] ?? '');
            if ($this->categoryMatches($category, $oldName)) {
                $metadata['files'][$file]['category'] = $this->renameCategoryPath($category, $oldName, $newName);
            }
        }

        $metadata['categories'] = $this->sortCategories(array_merge(
            $renamedCategories,
            $this->categoryPathsFor($newName)
        ));
        $this->writeMetadata($metadata);

        return $metadata['categories'];
    }

    public function deleteCategory(string $name): array
    {
        $name = $this->normalizeCategory($name);

        if ($name === '') {
            throw new \InvalidArgumentException('Category name cannot be empty.');
        }

        $metadata = $this->metadata();

        if (!in_array($name, $metadata['categories'], true)) {
            throw new \InvalidArgumentException('Category not found.');
        }

        $metadata['categories'] = array_values(array_filter(
            $metadata['categories'],
            fn(string $category): bool => !$this->categoryMatches($category, $name)
        ));

        foreach ($metadata['files'] as $file => $item) {
            if ($this->categoryMatches((string) ($item['category'] ?? ''), $name)) {
                unset($metadata['files'][$file]['category']);

                if (($metadata['files'][$file] ?? []) === []) {
                    unset($metadata['files'][$file]);
                }
            }
        }

        $this->writeMetadata($metadata);

        return $metadata['categories'];
    }

    public function assignCategory(string $file, string $category): ?array
    {
        $file = basename(rawurldecode($file));
        $path = $this->path($file);

        if (!is_file($path)) {
            return null;
        }

        $category = $this->normalizeCategory($category);
        $metadata = $this->metadata();

        $changed = false;
        foreach ($this->categoryPathsFor($category) as $categoryPath) {
            if (!in_array($categoryPath, $metadata['categories'], true)) {
                $metadata['categories'][] = $categoryPath;
                $changed = true;
            }
        }

        if ($changed) {
            $metadata['categories'] = $this->sortCategories($metadata['categories']);
        }

        if ($category === '') {
            unset($metadata['files'][$file]['category']);

            if (($metadata['files'][$file] ?? []) === []) {
                unset($metadata['files'][$file]);
            }
        } else {
            $metadata['files'][$file] ??= [];
            $metadata['files'][$file]['category'] = $category;
        }

        $this->writeMetadata($metadata);

        return $this->item($file, $metadata);
    }

    public function assignCategoryToMany(array $files, string $category): array
    {
        $category = $this->normalizeCategory($category);
        $metadata = $this->metadata();
        $updated = [];

        $changed = false;
        foreach ($this->categoryPathsFor($category) as $categoryPath) {
            if (!in_array($categoryPath, $metadata['categories'], true)) {
                $metadata['categories'][] = $categoryPath;
                $changed = true;
            }
        }

        if ($changed) {
            $metadata['categories'] = $this->sortCategories($metadata['categories']);
        }

        foreach ($this->normalizeFiles($files) as $file) {
            if (!is_file($this->path($file))) {
                continue;
            }

            if ($category === '') {
                unset($metadata['files'][$file]['category']);

                if (($metadata['files'][$file] ?? []) === []) {
                    unset($metadata['files'][$file]);
                }
            } else {
                $metadata['files'][$file] ??= [];
                $metadata['files'][$file]['category'] = $category;
            }

            $updated[] = $file;
        }

        if ($updated !== []) {
            $this->writeMetadata($metadata);
        }

        return array_map(fn(string $file): array => $this->item($file, $metadata), $updated);
    }

    public function delete(string $file): void
    {
        $file = basename(rawurldecode($file));
        $path = $this->path($file);

        if (is_file($path)) {
            unlink($path);
        }

        $this->deleteThumbnail($file);
        $this->deleteVariants($file);

        $metadata = $this->metadata();
        unset($metadata['files'][$file]);
        $this->writeMetadata($metadata);
    }

    public function deleteMany(array $files): array
    {
        $deleted = [];

        foreach ($this->normalizeFiles($files) as $file) {
            $path = $this->path($file);

            if (!is_file($path)) {
                continue;
            }

            unlink($path);
            $this->deleteThumbnail($file);
            $this->deleteVariants($file);
            $deleted[] = $file;
        }

        if ($deleted !== []) {
            $metadata = $this->metadata();

            foreach ($deleted as $file) {
                unset($metadata['files'][$file]);
            }

            $this->writeMetadata($metadata);
        }

        return $deleted;
    }

    public function rename(string $file, string $newName): ?array
    {
        $file = basename(rawurldecode($file));
        $path = $this->path($file);

        if (!is_file($path)) {
            return null;
        }

        $newName = $this->normalizeFilename($newName, $file);
        $target = $this->path($newName);

        if ($newName === $file) {
            return $this->item($file);
        }

        if (is_file($target)) {
            throw new \InvalidArgumentException('A media file with that name already exists.');
        }

        $metadata = $this->metadata();
        $fileMeta = $metadata['files'][$file] ?? null;

        if (!rename($path, $target)) {
            throw new \RuntimeException('Could not rename media file.');
        }

        $this->deleteThumbnail($file);
        $this->deleteVariants($file);

        if (is_array($fileMeta)) {
            $metadata['files'][$newName] = $fileMeta;
            unset($metadata['files'][$file]);
            $this->writeMetadata($metadata);
        }

        return $this->item($newName, $metadata);
    }

    public function item(string $file, ?array $metadata = null): array
    {
        $path = $this->path($file);
        $metadata ??= $this->metadata();
        $fileMeta = $metadata['files'][$file] ?? [];
        $uploadedAt = $fileMeta['uploaded_at'] ?? null;

        if ($uploadedAt === null || $uploadedAt === '') {
            $modifiedAt = filemtime($path);
            $uploadedAt = $modifiedAt === false ? null : gmdate('c', $modifiedAt);
        }

        $dimensions = $this->imageDimensions($path);

        return [
            'name'        => $file,
            'filename'    => $file,
            'size'        => (int) filesize($path),
            'mime'        => MimeDetector::detect($path),
            'thumb'       => $this->thumbnailDescriptor($file),
            'category'    => $this->categoryFor($file, $metadata),
            'uploaded_by' => $fileMeta['uploaded_by'] ?? null,
            'uploaded_at' => $uploadedAt,
            'width'       => $dimensions['width'],
            'height'      => $dimensions['height'],
            'alt'         => $fileMeta['alt'] ?? '',
            'title'       => $fileMeta['title'] ?? '',
            'visibility'  => $fileMeta['visibility'] ?? 'public',
        ];
    }

    public function find(string $file): ?array
    {
        $file = basename(rawurldecode($file));

        if (!is_file($this->path($file))) {
            return null;
        }

        return $this->item($file);
    }

    public function setUploadedBy(string $file, string $userId): void
    {
        $file = basename($file);
        $metadata = $this->metadata();
        $metadata['files'][$file] ??= [];
        $metadata['files'][$file]['uploaded_by'] = $userId;
        $metadata['files'][$file]['uploaded_at'] = gmdate('c');
        $this->writeMetadata($metadata);
    }

    public function updateMeta(string $file, string $alt, string $title): ?array
    {
        $file = basename(rawurldecode($file));
        $path = $this->path($file);

        if (!is_file($path)) {
            return null;
        }

        $alt   = substr(trim($alt), 0, 500);
        $title = substr(trim($title), 0, 500);

        $metadata = $this->metadata();
        $metadata['files'][$file] ??= [];

        if ($alt !== '') {
            $metadata['files'][$file]['alt'] = $alt;
        } else {
            unset($metadata['files'][$file]['alt']);
        }

        if ($title !== '') {
            $metadata['files'][$file]['title'] = $title;
        } else {
            unset($metadata['files'][$file]['title']);
        }

        if ($metadata['files'][$file] === []) {
            unset($metadata['files'][$file]);
        }

        $this->writeMetadata($metadata);

        return $this->item($file, $metadata);
    }

    public function updateVisibility(string $file, string $visibility): ?array
    {
        $file = basename(rawurldecode($file));
        $path = $this->path($file);

        if (!is_file($path)) {
            return null;
        }

        $visibility = $visibility === 'private' ? 'private' : 'public';

        $metadata = $this->metadata();
        $metadata['files'][$file] ??= [];

        if ($visibility === 'private') {
            $metadata['files'][$file]['visibility'] = 'private';
        } else {
            unset($metadata['files'][$file]['visibility']);

            if ($metadata['files'][$file] === []) {
                unset($metadata['files'][$file]);
            }
        }

        $this->writeMetadata($metadata);

        return $this->item($file, $metadata);
    }

    public function updateVisibilityForMany(array $files, string $visibility): array
    {
        $visibility = $visibility === 'private' ? 'private' : 'public';
        $metadata = $this->metadata();
        $updated = [];

        foreach ($this->normalizeFiles($files) as $file) {
            if (!is_file($this->path($file))) {
                continue;
            }

            $metadata['files'][$file] ??= [];

            if ($visibility === 'private') {
                $metadata['files'][$file]['visibility'] = 'private';
            } else {
                unset($metadata['files'][$file]['visibility']);

                if ($metadata['files'][$file] === []) {
                    unset($metadata['files'][$file]);
                }
            }

            $updated[] = $file;
        }

        if ($updated !== []) {
            $this->writeMetadata($metadata);
        }

        return array_map(fn(string $file): array => $this->item($file, $metadata), $updated);
    }

    public function isPrivate(string $file): bool
    {
        $file = basename(rawurldecode($file));
        $metadata = $this->metadata();

        return ($metadata['files'][$file]['visibility'] ?? 'public') === 'private';
    }

    public function thumbnailPath(string $file): ?string
    {
        $file = basename(rawurldecode($file));

        if (!is_file($this->path($file))) {
            return null;
        }

        return $this->ensureThumbnail($file);
    }

    /**
     * Return the configured, cache-friendly variants advertised by the API.
     */
    public function variantDescriptor(string $file): ?array
    {
        $file = basename(rawurldecode($file));
        $path = $this->path($file);

        if (!(bool) comet_config('media.variants.enabled', true) || !is_file($path) || !$this->isThumbnailSource($file) || !$this->hasThumbnailGenerator($file)) {
            return null;
        }

        $dimensions = $this->imageDimensions($path);
        $sourceWidth = (int) ($dimensions['width'] ?? 0);
        $maxDimension = max(1, (int) comet_config('media.variants.max_dimension', 4096));
        $widths = array_values(array_unique(array_filter(array_map(
            static fn(mixed $width): int => (int) $width,
            (array) comet_config('media.variants.widths', [320, 640, 960, 1280, 1920])
        ), static fn(int $width): bool => $width > 0 && $width <= $maxDimension && $width < $sourceWidth)));
        sort($widths);

        return [
            'widths' => $widths,
            'formats' => $this->availableVariantFormats(),
            'default_format' => $this->defaultVariantFormat(),
            'custom_sizes' => (bool) comet_config('media.variants.allow_custom_sizes', false),
            'max_dimension' => $maxDimension,
        ];
    }

    /**
     * Create or reuse an image derivative. Originals are never enlarged.
     *
     * @return array{path:string,mime:string,width:int,height:int}
     */
    public function variant(string $file, array $options): array
    {
        $file = basename(rawurldecode($file));
        $source = $this->path($file);

        if (!(bool) comet_config('media.variants.enabled', true)) {
            throw new \InvalidArgumentException('Image variants are disabled.');
        }

        if (!is_file($source) || !$this->isThumbnailSource($file) || !$this->hasThumbnailGenerator($file)) {
            throw new \InvalidArgumentException('This file cannot be resized.');
        }

        $width = filter_var($options['w'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $height = filter_var($options['h'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $maxDimension = max(1, (int) comet_config('media.variants.max_dimension', 4096));

        if (($width <= 0 && $height <= 0) || $width > $maxDimension || $height > $maxDimension) {
            throw new \InvalidArgumentException('Choose a positive variant width or height within the configured limit.');
        }

        $allowCustom = (bool) comet_config('media.variants.allow_custom_sizes', false);
        $allowedWidths = array_map('intval', (array) comet_config('media.variants.widths', [320, 640, 960, 1280, 1920]));

        if (!$allowCustom && ($height > 0 || !in_array($width, $allowedWidths, true))) {
            throw new \InvalidArgumentException('Choose one of the configured variant widths.');
        }

        $fitValue = $options['fit'] ?? 'contain';
        $fit = is_scalar($fitValue) ? strtolower(trim((string) $fitValue)) : '';
        if (!in_array($fit, ['contain', 'cover'], true)) {
            throw new \InvalidArgumentException('Variant fit must be contain or cover.');
        }

        $formatValue = $options['format'] ?? $this->defaultVariantFormat();
        $format = is_scalar($formatValue) ? strtolower(trim((string) $formatValue)) : '';
        $format = $format === 'jpg' ? 'jpeg' : $format;
        if (!in_array($format, $this->availableVariantFormats(), true)) {
            throw new \InvalidArgumentException('Requested variant format is not available.');
        }

        $sourceImage = $this->createImageResource($source, $file);
        if (!$sourceImage instanceof \GdImage) {
            throw new \RuntimeException('Could not decode the source image.');
        }

        $sourceImage = $this->applyExifOrientation($sourceImage, $source, $file);
        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);
        [$targetWidth, $targetHeight, $sourceX, $sourceY, $cropWidth, $cropHeight] = $this->variantGeometry(
            $sourceWidth,
            $sourceHeight,
            $width,
            $height,
            $fit,
        );

        $maxPixels = max(1, (int) comet_config('media.variants.max_pixels', 16000000));
        if ($targetWidth * $targetHeight > $maxPixels) {
            imagedestroy($sourceImage);
            throw new \InvalidArgumentException('Requested variant exceeds the configured pixel limit.');
        }

        $extension = $format === 'jpeg' ? 'jpg' : $format;
        $mime = 'image/' . $format;
        $sourceFingerprint = sha1($file . '|' . (string) filesize($source) . '|' . (string) filemtime($source));
        $transform = sha1(json_encode([$targetWidth, $targetHeight, $fit, $format, (int) comet_config('media.variants.quality', 82)]));
        $directory = $this->variantStorageDirectory($file);
        $target = $directory . '/' . $sourceFingerprint . '-' . $transform . '.' . $extension;

        if (is_file($target)) {
            imagedestroy($sourceImage);
            return ['path' => $target, 'mime' => $mime, 'width' => $targetWidth, 'height' => $targetHeight];
        }

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            imagedestroy($sourceImage);
            throw new \RuntimeException('Could not create the image variant cache directory.');
        }

        $variant = imagecreatetruecolor($targetWidth, $targetHeight);
        if (!$variant instanceof \GdImage) {
            imagedestroy($sourceImage);
            throw new \RuntimeException('Could not allocate the image variant.');
        }

        $this->prepareVariantCanvas($variant, $format);
        imagecopyresampled($variant, $sourceImage, 0, 0, $sourceX, $sourceY, $targetWidth, $targetHeight, $cropWidth, $cropHeight);
        $tmp = $target . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $saved = $this->saveVariantImage($variant, $tmp, $format);
        imagedestroy($variant);
        imagedestroy($sourceImage);

        if (!$saved) {
            if (is_file($tmp)) {
                unlink($tmp);
            }
            throw new \RuntimeException('Could not encode the image variant.');
        }

        rename($tmp, $target);
        $this->pruneVariants($directory, $target);

        return ['path' => $target, 'mime' => $mime, 'width' => $targetWidth, 'height' => $targetHeight];
    }

    public function regenerateThumbnails(array $files = []): array
    {
        $generated = 0;
        $failed = 0;
        $skipped = 0;
        $enabled = (bool) comet_config('media.thumbnails.enabled', true);
        $targets = $files === [] ? (scandir($this->mediaDir) ?: []) : $files;

        foreach ($targets as $file) {
            if (!is_string($file) || $file === '') {
                continue;
            }

            $file = basename(rawurldecode($file));

            if ($file === '' || $file[0] === '.' || !is_file($this->path($file))) {
                continue;
            }

            if (!$enabled || !$this->isThumbnailSource($file)) {
                $skipped++;
                continue;
            }

            if (!$this->hasThumbnailGenerator($file)) {
                $failed++;
                continue;
            }

            $this->deleteThumbnail($file);
            $path = $this->ensureThumbnail($file);

            if ($path !== null) {
                $generated++;
            } else {
                $failed++;
            }
        }

        return [
            'generated' => $generated,
            'failed' => $failed,
            'skipped' => $skipped,
        ];
    }

    public function setUploadedCategory(string $file, string $category): array
    {
        return $this->assignCategory($file, $category) ?? $this->item($file);
    }

    private function path(string $file): string
    {
        return $this->mediaDir . '/' . basename($file);
    }

    private function imageDimensions(string $path): array
    {
        $size = @getimagesize($path);

        if (!is_array($size)) {
            return ['width' => null, 'height' => null];
        }

        return [
            'width' => (int) ($size[0] ?? 0) ?: null,
            'height' => (int) ($size[1] ?? 0) ?: null,
        ];
    }

    private function thumbnailDescriptor(string $file): ?array
    {
        if (!(bool) comet_config('media.thumbnails.enabled', true) || !$this->isThumbnailSource($file)) {
            return null;
        }

        return [
            'max_size' => max(64, min(4096, (int) comet_config('media.thumbnails.size', 512))),
            'mime' => 'image/jpeg',
        ];
    }

    private function ensureThumbnail(string $file): ?string
    {
        if (!(bool) comet_config('media.thumbnails.enabled', true)) {
            return null;
        }

        $source = $this->path($file);

        if (!is_file($source) || !$this->isThumbnailSource($file)) {
            return null;
        }

        $target = $this->thumbnailStoragePath($file);

        if (is_file($target) && (filemtime($target) ?: 0) >= (filemtime($source) ?: 0)) {
            return $target;
        }

        return $this->generateThumbnail($source, $target, $file) ? $target : null;
    }

    private function isThumbnailSource(string $file): bool
    {
        $ext = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'], true);
    }

    private function thumbnailStoragePath(string $file): string
    {
        return $this->thumbDir . '/' . sha1(basename($file)) . '.jpg';
    }

    private function deleteThumbnail(string $file): void
    {
        $path = $this->thumbnailStoragePath($file);

        if (is_file($path)) {
            unlink($path);
        }
    }

    private function generateThumbnail(string $source, string $target, string $file): bool
    {
        if (!$this->hasThumbnailGenerator($file)) {
            return false;
        }

        $sourceImage = $this->createImageResource($source, $file);

        if (!$sourceImage instanceof \GdImage) {
            return false;
        }

        $sourceImage = $this->applyExifOrientation($sourceImage, $source, $file);
        $width = imagesx($sourceImage);
        $height = imagesy($sourceImage);

        if ($width <= 0 || $height <= 0) {
            imagedestroy($sourceImage);

            return false;
        }

        $maxSize = max(64, min(4096, (int) comet_config('media.thumbnails.size', 512)));
        $scale = min(1, $maxSize / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $thumbnail = imagecreatetruecolor($targetWidth, $targetHeight);

        if (!$thumbnail instanceof \GdImage) {
            imagedestroy($sourceImage);

            return false;
        }

        $background = imagecolorallocate($thumbnail, 255, 255, 255);
        imagefilledrectangle($thumbnail, 0, 0, $targetWidth, $targetHeight, $background === false ? 0 : $background);
        imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $quality = max(1, min(100, (int) comet_config('media.thumbnails.quality', 82)));
        $tmp = $target . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $saved = imagejpeg($thumbnail, $tmp, $quality);

        imagedestroy($thumbnail);
        imagedestroy($sourceImage);

        if (!$saved) {
            if (is_file($tmp)) {
                unlink($tmp);
            }

            return false;
        }

        rename($tmp, $target);

        return true;
    }

    private function variantGeometry(int $sourceWidth, int $sourceHeight, int $width, int $height, string $fit): array
    {
        if ($fit === 'cover' && $width > 0 && $height > 0) {
            $scale = min(1, max($width / $sourceWidth, $height / $sourceHeight));
            $targetWidth = max(1, min($width, (int) round($sourceWidth * $scale)));
            $targetHeight = max(1, min($height, (int) round($sourceHeight * $scale)));
            $cropWidth = max(1, min($sourceWidth, (int) round($targetWidth / $scale)));
            $cropHeight = max(1, min($sourceHeight, (int) round($targetHeight / $scale)));

            return [
                $targetWidth,
                $targetHeight,
                max(0, (int) floor(($sourceWidth - $cropWidth) / 2)),
                max(0, (int) floor(($sourceHeight - $cropHeight) / 2)),
                $cropWidth,
                $cropHeight,
            ];
        }

        $widthScale = $width > 0 ? $width / $sourceWidth : PHP_FLOAT_MAX;
        $heightScale = $height > 0 ? $height / $sourceHeight : PHP_FLOAT_MAX;
        $scale = min(1, $widthScale, $heightScale);

        return [
            max(1, (int) round($sourceWidth * $scale)),
            max(1, (int) round($sourceHeight * $scale)),
            0,
            0,
            $sourceWidth,
            $sourceHeight,
        ];
    }

    private function prepareVariantCanvas(\GdImage $image, string $format): void
    {
        if (in_array($format, ['png', 'webp', 'avif'], true)) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
            imagefilledrectangle($image, 0, 0, imagesx($image), imagesy($image), $transparent === false ? 0 : $transparent);
            return;
        }

        $background = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, imagesx($image), imagesy($image), $background === false ? 0 : $background);
    }

    private function saveVariantImage(\GdImage $image, string $path, string $format): bool
    {
        $quality = max(1, min(100, (int) comet_config('media.variants.quality', 82)));

        return match ($format) {
            'jpeg' => imagejpeg($image, $path, $quality),
            'png' => imagepng($image, $path, max(0, min(9, (int) round((100 - $quality) * 9 / 100)))),
            'webp' => imagewebp($image, $path, $quality),
            'avif' => imageavif($image, $path, $quality),
            default => false,
        };
    }

    private function availableVariantFormats(): array
    {
        $encoders = [
            'jpeg' => 'imagejpeg',
            'png' => 'imagepng',
            'webp' => 'imagewebp',
            'avif' => 'imageavif',
        ];
        $formats = [];

        foreach ((array) comet_config('media.variants.formats', ['jpeg', 'png', 'webp']) as $format) {
            $format = strtolower((string) $format);
            $format = $format === 'jpg' ? 'jpeg' : $format;
            if (isset($encoders[$format]) && function_exists($encoders[$format])) {
                $formats[] = $format;
            }
        }

        return array_values(array_unique($formats));
    }

    private function defaultVariantFormat(): string
    {
        $formats = $this->availableVariantFormats();
        $default = strtolower((string) comet_config('media.variants.default_format', 'webp'));
        $default = $default === 'jpg' ? 'jpeg' : $default;

        return in_array($default, $formats, true) ? $default : (string) ($formats[0] ?? 'jpeg');
    }

    private function variantStorageDirectory(string $file): string
    {
        return $this->variantDir . '/' . sha1(basename($file));
    }

    private function deleteVariants(string $file): void
    {
        $directory = $this->variantStorageDirectory($file);
        if (!is_dir($directory)) {
            return;
        }

        foreach (glob($directory . '/*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        rmdir($directory);
    }

    private function pruneVariants(string $directory, string $current): void
    {
        $maximum = max(1, (int) comet_config('media.variants.max_variants_per_image', 20));
        $files = array_values(array_filter(glob($directory . '/*') ?: [], 'is_file'));
        usort($files, static fn(string $a, string $b): int => (filemtime($a) ?: 0) <=> (filemtime($b) ?: 0));

        while (count($files) > $maximum) {
            $path = array_shift($files);
            if ($path === $current) {
                $files[] = $path;
                continue;
            }
            unlink($path);
        }
    }

    private function applyExifOrientation(\GdImage $image, string $sourcePath, string $file): \GdImage
    {
        $ext = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));

        if (!in_array($ext, ['jpg', 'jpeg'], true) || !function_exists('exif_read_data')) {
            return $image;
        }

        $rawExif = @exif_read_data($sourcePath);

        if (!is_array($rawExif)) {
            return $image;
        }

        $orientation = (int) ($rawExif['Orientation'] ?? 1);

        return match ($orientation) {
            2 => $this->flipImage($image, IMG_FLIP_HORIZONTAL),
            3 => $this->rotateImage($image, 180),
            4 => $this->flipImage($image, IMG_FLIP_VERTICAL),
            5 => $this->rotateImage($this->flipImage($image, IMG_FLIP_HORIZONTAL), -90),
            6 => $this->rotateImage($image, -90),
            7 => $this->rotateImage($this->flipImage($image, IMG_FLIP_HORIZONTAL), 90),
            8 => $this->rotateImage($image, 90),
            default => $image,
        };
    }

    private function rotateImage(\GdImage $image, int $degrees): \GdImage
    {
        if (!function_exists('imagerotate') || $degrees === 0) {
            return $image;
        }

        $background = imagecolorallocate($image, 255, 255, 255);
        $rotated = @imagerotate($image, $degrees, $background === false ? 0 : $background);

        if (!$rotated instanceof \GdImage) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }

    private function flipImage(\GdImage $image, int $mode): \GdImage
    {
        if (!function_exists('imageflip')) {
            return $image;
        }

        if (!@imageflip($image, $mode)) {
            return $image;
        }

        return $image;
    }

    private function createImageResource(string $path, string $file): ?\GdImage
    {
        $ext = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) ?: null : null,
            'png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) ?: null : null,
            'gif' => function_exists('imagecreatefromgif') ? @imagecreatefromgif($path) ?: null : null,
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) ?: null : null,
            'avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($path) ?: null : null,
            default => null,
        };
    }

    private function hasThumbnailGenerator(string $file): bool
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagecopyresampled') || !function_exists('imagejpeg')) {
            return false;
        }

        $ext = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => function_exists('imagecreatefromjpeg'),
            'png' => function_exists('imagecreatefrompng'),
            'gif' => function_exists('imagecreatefromgif'),
            'webp' => function_exists('imagecreatefromwebp'),
            'avif' => function_exists('imagecreatefromavif'),
            default => false,
        };
    }

    private function normalizeFiles(array $files): array
    {
        $names = array_map(
            static fn(mixed $file): string => basename(rawurldecode((string) $file)),
            $files
        );

        return array_values(array_unique(array_filter(
            $names,
            static fn(string $file): bool => $file !== ''
        )));
    }

    private function normalizeFilename(string $name, string $currentFile): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', trim($name)) ?? '';

        if ($name !== basename($name)) {
            throw new \InvalidArgumentException('Filename cannot contain folders.');
        }

        if ($name === '' || $name === '.' || $name === '..' || str_starts_with($name, '.')) {
            throw new \InvalidArgumentException('Filename cannot be empty or hidden.');
        }

        if (pathinfo($name, PATHINFO_EXTENSION) === '') {
            $extension = pathinfo($currentFile, PATHINFO_EXTENSION);
            if ($extension !== '') {
                $name .= '.' . $extension;
            }
        }

        if (strlen($name) > 180) {
            throw new \InvalidArgumentException('Filename is too long.');
        }

        return $name;
    }

    private function categoryFor(string $file, array $metadata): string
    {
        $category = (string) ($metadata['files'][$file]['category'] ?? '');

        return in_array($category, $metadata['categories'], true) ? $category : '';
    }

    private function categoryMatches(string $category, string $categoryPath): bool
    {
        return MediaCategory::matches($category, $categoryPath);
    }

    private function renameCategoryPath(string $category, string $oldName, string $newName): string
    {
        return MediaCategory::renamePath($category, $oldName, $newName);
    }

    private function metadata(): array
    {
        $decoded = is_file($this->metadataPath)
            ? json_decode((string) file_get_contents($this->metadataPath), true)
            : [];

        $metadata = is_array($decoded) ? $decoded : [];
        $categories = [];
        foreach ((array) ($metadata['categories'] ?? []) as $category) {
            $categories = array_merge($categories, $this->categoryPathsFor((string) $category));
        }
        $categories = $this->sortCategories($categories);

        $files = [];
        foreach ((array) ($metadata['files'] ?? []) as $file => $item) {
            $file = basename((string) $file);
            $category = $this->normalizeCategory((string) ($item['category'] ?? ''));

            if ($file !== '' && is_file($this->path($file))) {
                if ($category !== '' && !in_array($category, $categories, true)) {
                    $categories = array_merge($categories, $this->categoryPathsFor($category));
                }

                $entry = [];
                if ($category !== '') {
                    $entry['category'] = $category;
                }
                if (!empty($item['uploaded_by'])) {
                    $entry['uploaded_by'] = (string) $item['uploaded_by'];
                }
                if (!empty($item['uploaded_at'])) {
                    $entry['uploaded_at'] = (string) $item['uploaded_at'];
                }
                if (isset($item['alt']) && $item['alt'] !== '') {
                    $entry['alt'] = (string) $item['alt'];
                }
                if (isset($item['title']) && $item['title'] !== '') {
                    $entry['title'] = (string) $item['title'];
                }
                if (isset($item['visibility']) && $item['visibility'] === 'private') {
                    $entry['visibility'] = 'private';
                }

                if ($entry !== []) {
                    $files[$file] = $entry;
                }
            }
        }

        $categories = $this->sortCategories($categories);

        return [
            'categories' => array_values($categories),
            'files' => $files,
        ];
    }

    private function writeMetadata(array $metadata): void
    {
        $payload = [
            'categories' => $this->sortCategories((array) ($metadata['categories'] ?? [])),
            'files' => $metadata['files'] ?? [],
        ];
        $tmp = $this->metadataPath . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new \RuntimeException('Could not encode media metadata.');
        }

        file_put_contents($tmp, $json . PHP_EOL, LOCK_EX);
        rename($tmp, $this->metadataPath);
    }

    private function normalizeCategory(string $category): string
    {
        return MediaCategory::normalize($category);
    }

    private function categoryPathsFor(string $category): array
    {
        return MediaCategory::pathsFor($category);
    }

    private function sortCategories(array $categories): array
    {
        return MediaCategory::sort($categories);
    }
}
