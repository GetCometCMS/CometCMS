# CometCMS Migrator for WordPress

This WordPress plugin migrates public WordPress post types into CometCMS through the CometCMS public REST API.

## Features

- Admin screen under **Tools > CometCMS Migrator**
- API key based connection to a CometCMS workspace. Use the CometCMS site root URL, for example `https://cms.example.com`, not `/admin/dashboard`.
- Batch migration for posts, pages, and other public post types
- Optional creation of missing CometCMS content types
- Featured image and attached media upload
- Advanced Custom Fields and ACF Pro field migration
- ACF option page migration into per-page single-entry CometCMS content types
- Repeatable updates using stored CometCMS entry IDs in WordPress post meta

## Required CometCMS Token Grants

Create an API token in CometCMS with grants for the target workspace:

- `schema.create` on `schema:*` if the plugin should create content types
- `schema.update` on `schema:*` if the plugin should add newly detected ACF fields to existing content types
- `content.create`, `content.update`, and `content.publish` on `content:*`
- `content.read` on `content:*` for update detection
- `media.upload` and `media.update` on `media:*` when migrating media

## Default Schema

For each enabled WordPress post type, the plugin creates a CometCMS content type with:

- `title`
- `slug`
- `wordpress_id`
- `wordpress_type`
- `excerpt`
- `content`
- `featured_image`
- `attachments`
- `original_url`

WordPress content is stored as HTML in the `content` textarea field.

## ACF Support

When ACF migration is enabled, the plugin reads field objects from each migrated post and adds missing fields to the CometCMS content type. Detected ACF option pages appear in Content Mapping and can be migrated into their own single-entry CometCMS content types.

Native mappings:

- text-like fields -> `text`
- textarea and WYSIWYG -> `textarea`
- number and range -> `number` / `range`
- true/false -> `boolean`
- select, radio, button group, checkbox -> `select`
- date and date-time pickers -> `date` / `datetime`
- color picker -> `color`
- image, file, and gallery -> `media`
- repeater -> `repeater` with mapped subfields

Flexible content, group, clone, relationship, post object, taxonomy, user, and link are stored as `json`.
