# Media Library

## Uploading files

1. Click **Media** in the sidebar.
2. Click **Upload** and select one or more files.

Supported formats include JPEG, PNG, WebP, GIF, SVG, AVIF, MP4, WebM, QuickTime/MOV, M4V, AVI, MKV, common audio formats, archives, and common document types.

The bundled CMS limit is 1 GiB per file, and the admin upload request has no browser-side timeout. Your PHP and web-server limits still apply. The included Apache/mod_php and PHP-FPM defaults set `upload_max_filesize` to `1G` and `post_max_size` to `1100M`; when using Nginx, a reverse proxy, or a hosting control panel, configure its body-size and request-timeout limits to match. You can lower the CMS limit with `media.max_upload_bytes` in `config/config.php`.

![Media library view in the CometCMS admin](../screenshots/view-media.png)

## Organising with categories

Use a file's menu or detail panel to assign it to a category. Categories help you keep large libraries organised. Categories can be nested for finer-grained organisation, for example `Brand / Logos` or `Products / Campaigns`.

## Alt text and title

Open any file to reveal the detail panel. You can set:

- **Alt text** — a short description used by screen readers and as the `alt` attribute in HTML.
- **Title** — an optional tooltip shown when a user hovers over the image.

Both fields are included in the public API response and are saved automatically when you leave the field.

## Visibility

Each file can be set to **Public** (default) or **Private**:

- **Public** — the file is served to everyone and appears in unauthenticated API responses.
- **Private** — the file is hidden from unauthenticated `GET /api/v1/workspaces/{workspace}/media` responses, and fetching the file directly via `GET /media/{workspace}/{filename}` requires a bearer token with `media.read` permission for that file.

Change visibility in the detail panel or use the bulk **Set field → Visibility** action to update multiple files at once.

## Using media in content

Fields of type `media` let you pick a file from the media library directly within the content editor.

## Accessing media files

Uploaded files are served from:

```
/media/{workspace}/{filename}
```

### Responsive image variants

Raster images can be resized on demand by adding a configured width to the original media URL:

```text
/media/{workspace}/{filename}?w=640&format=webp
```

The original is never changed or enlarged. Generated variants are stored in the workspace's disposable `media-variants` cache, reused on subsequent requests, and omitted from backups. Deleting or renaming the original also clears its variants.

By default, accepted widths are `320`, `640`, `960`, `1280`, and `1920` pixels. The media API returns ready-to-use URLs for the configured widths that are smaller than each original. Supported output formats depend on the installed GD image encoders and the `media.variants.formats` configuration.

Projects that need exact dimensions can enable `media.variants.allow_custom_sizes`. This additionally enables `h` and the `fit` modes `contain` and `cover`:

```text
/media/{workspace}/{filename}?w=800&h=450&fit=cover&format=webp
```

`contain` preserves the entire image within the requested bounds. `cover` performs a centered crop when both dimensions are supplied. Neither mode upscales an image. `max_dimension`, `max_pixels`, and `max_variants_per_image` bound resource and cache usage.

Example configuration:

```php
'variants' => [
    'enabled' => true,
    'widths' => [320, 640, 960, 1280, 1920],
    'allow_custom_sizes' => false,
    'max_dimension' => 4096,
    'max_pixels' => 16000000,
    'max_variants_per_image' => 20,
    'formats' => ['jpeg', 'png', 'webp'],
    'default_format' => 'webp',
    'quality' => 82,
],
```

## Deleting files

Select a file and click **Delete**. This permanently removes the file and its metadata.
