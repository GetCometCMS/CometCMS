# Legacy handling

Compatibility code for data written by older releases, including old inputs that are normalized or deliberately discarded. Each entry says what it handles, where it lives, and what makes it safe to remove. Add new entries when you add compatibility code, and delete them together with the code.

"Safe to remove" usually means: the relevant migrations have completed successfully on every installation, **and** backups from before that release no longer need to be restorable.

Reviewed against the repository on 2026-10-18. These are removal conditions, not claims that deployed installations already meet them. No installation or backup inventory was available for this review.

Before removing a handler, check every workspace (including archived ones), revisions, trash, configuration-defined schemas, and supported backups where applicable. Reading normalized data does not prove the underlying JSON has been rewritten. Old clients and integrations can also keep producing old shapes after stored data has been converted.

## Website and theme

### Website files outside the theme folder

- **Handles:** `site/regions/`, `site/layouts/` and `site/components/` from before everything design-related lived in `site/theme/`.
- **Where:** [ThemeMigration.php](cms/app/Site/ThemeMigration.php) `moveLegacyDirectories()`, run lazily from the [SiteRepository constructor](cms/app/Site/SiteRepository.php#L66) and after every restore ([RestoreService.php:156](cms/app/Backups/RestoreService.php#L156)). Old backup paths are mapped into the theme folder by [RestoreService::siteTarget()](cms/app/Backups/RestoreService.php#L306).
- **Safe to remove:** once every workspace has been migrated and old backups need not be restorable. Then drop `moveLegacyDirectories()`, the `needed()` check in the constructor, and `siteTarget()`.

### Generated component ids

- **Handles:** components named `c` + 10 hex chars (e.g. `ce1f4a17586`) from before ids were slugs of the component name.
- **Where:**
  - [ThemeMigration.php](cms/app/Site/ThemeMigration.php) `renameLegacyComponents()`, `moveComponentRevisions()`, `rewriteReferences()`. These rename the files, move `revisions/site/components/{old}/`, and rewrite placements in theme files and revisions.
  - `site/component-aliases.json` maps old id → new id. Restores never overwrite it ([RestoreService.php:95](cms/app/Backups/RestoreService.php#L95)).
  - [SiteRepository::component()](cms/app/Site/SiteRepository.php#L393) resolves old ids through the alias file for stale clients. Revision methods use the resolved id.
  - [SiteRepository::componentId()](cms/app/Site/SiteRepository.php) never generates a slug matching `ThemeMigration::LEGACY_COMPONENT_ID`, so the migration can't mistake a new component for an old one.
  - [RestoreService::siteTarget()](cms/app/Backups/RestoreService.php#L306) restores `site/components/{old}.json` onto the aliased file, so restoring without overwrite doesn't duplicate components.
- **Safe to remove:** together with the entry above, after references in files and revisions have been rewritten and stale clients using generated ids have expired. Then the alias file can be deleted and `componentId()` loses its legacy-pattern check.

### Stored `updated_at` in theme files

- **Handles:** `theme.json`, components, layouts and regions written with an `updated_at` field. Theme files no longer store save times, and `updated_at` comes from the file modification time.
- **Where:** fallbacks in [SiteRepository::theme()](cms/app/Site/SiteRepository.php#L172) and `readComponent()` ([SiteRepository.php:674](cms/app/Site/SiteRepository.php#L674)). `deleteComponent()` strips the field when it rewrites placements. The migration turns a component's stored time into its file time. Layouts, regions and `theme.json` lose the field on their next save.
- **Safe to remove:** after stored times have been converted to file times or the affected files have been saved, and old backups need not be restorable. Then drop both `?:` fallbacks and the `unset()` in `deleteComponent()`.

### `site/theme.json` (tokens and CSS in one file)

- **Handles:** themes from before `site/theme/theme.json` + `site/theme/style.css`. A restored legacy file takes precedence over the newer files.
- **Where:** [SiteRepository::theme()](cms/app/Site/SiteRepository.php#L159) and `saveTheme()`, which deletes the legacy file. [RestoreService.php:100](cms/app/Backups/RestoreService.php#L100) treats both formats as one backup item, and [RestoreService.php:132](cms/app/Backups/RestoreService.php#L132) removes the legacy file after restoring the new one.
- **Safe to remove:** after every workspace has saved or migrated its theme into the split files, and old backups need not be restorable. Reading a theme does not migrate it.

### Per-type card layouts (`layouts/{type}/card.json`)

- **Handles:** card layouts from before cards lived in collections. They still render as the type's standard card.
- **Where:** [SiteRepository::cardTemplate()](cms/app/Site/SiteRepository.php#L356), [LayoutSchema::cardTemplate()](cms/app/Site/LayoutSchema.php#L497). The extra `'card'` variant is also handled in `componentUsages()` ([SiteRepository.php:485](cms/app/Site/SiteRepository.php#L485)), `deleteComponent()` and `forgetType()` ([SiteRepository.php:939](cms/app/Site/SiteRepository.php#L939)).
- **Safe to remove:** after a migration that turns each `card.json` into the type's collection card. Nothing migrates it today. Include old revisions and supported backups in the conversion or retire them explicitly.

### Layout format versions

- **Handles:** layouts, regions, components and revisions with a `format_version` other than `LayoutSchema::FORMAT_VERSION` (2). They're ignored rather than deleted: the builder shows defaults and an upgrade notice, and old revisions can't be restored.
- **Where:** `needsLayoutUpgrade()` / `needsRegionUpgrade()` ([SiteRepository.php:764](cms/app/Site/SiteRepository.php#L764)), the revision filter in `listRevisions()` ([SiteRepository.php:898](cms/app/Site/SiteRepository.php#L898)), `upgrade_required` in [Admin/SiteController.php:206](cms/app/Controllers/Admin/SiteController.php#L206) and [:245](cms/app/Controllers/Admin/SiteController.php#L245), and `upgradeRequired` in [PageBuilderEditorView.vue:603](web/src/views/PageBuilderEditorView.vue#L603).
- **Safe to remove:** keep the generic version gate for future format bumps. Format-1-specific UI text or fixtures can go once those files, revisions and supported backups have been migrated, replaced or explicitly retired. Changing the version number alone does not convert a design.

### String borders

- **Handles:** `style.border` stored as `'all'`, `'top'` or `'bottom'` instead of a list of sides.
- **Where:** `LEGACY_BORDERS` in [LayoutSchema.php:821](cms/app/Site/LayoutSchema.php#L821).
- **Safe to remove:** after rewriting layouts, regions, components and their revisions, and retiring or converting old backups and old callers that still submit strings. Saving current designs alone leaves old revision snapshots untouched.

### Background images in node props

- **Handles:** `background_image`, `background_size`, `background_position`, `background_repeat`, `background_blur` and `overlay` stored in `props` before they became style properties. An existing non-null style value wins over the old prop.
- **Where:** [LayoutSchema.php](cms/app/Site/LayoutSchema.php) `normalizeNodes()` calls `liftBackgroundImage()` before normalizing props and styles. Covered by [SiteTest.php](tests/php/SiteTest.php), in the background-image tests.
- **Safe to remove:** after rewriting affected layouts, regions, components and revisions, and converting or retiring old backups and callers. Read-time normalization does not persist the conversion.
- **Streamlining:** remove `liftBackgroundImage()` and its call so props and styles can be normalized directly. Keep `BACKGROUND_IMAGE_KEYS`: it also defines the current style options.

### Boolean section stickiness

- **Handles:** section `props.sticky: true/false`, converted to `'always'` / `'none'`.
- **Where:** [LayoutSchema::normalizeProps()](cms/app/Site/LayoutSchema.php). Covered by the section scroll-behavior test in [SiteTest.php](tests/php/SiteTest.php).
- **Safe to remove:** after rewriting affected designs and revisions and converting or retiring old backups and callers. Saving a design normalizes its current nodes; it does not rewrite every revision.
- **Streamlining:** delete the boolean conversion and accept only the current sticky enum through the ordinary prop spec.

### Theme density presets and `radius: 'xl'`

- **Handles:** stored `spacing: 'compact' | 'normal' | 'relaxed'` presets and `radius: 'xl'`. Missing `space` tokens are derived from the density factor; explicit tokens win. The old radius becomes `'lg'`.
- **Where:** [Theme.php](cms/app/Site/Theme.php) `space()`, `SPACING` and the radius branch in `normalize()`. Covered by the spacing/radius-token test in [SiteTest.php](tests/php/SiteTest.php).
- **Safe to remove:** after writing explicit `space` tokens and the current radius into every affected theme and theme revision, and converting or retiring old backups and callers. `saveTheme()` converts the current theme, but restoring an older theme revision still passes through `saveTheme()` and `Theme::normalize()`.
- **Streamlining:** remove the backend density factor and `xl` alias. Keep the preset UI in [SiteDesignPanel.vue](web/src/components/site/builder/SiteDesignPanel.vue): it already writes explicit `space` values and remains a current editing feature.

### Standalone forms (`site/forms/{id}.json`)

- **Handles:** forms from before submission-enabled content types became the forms. They keep their ids and placements.
- **Where:** [FormRepository.php](cms/app/Site/FormRepository.php) (class doc, `assertLegacyForm()` at [:267](cms/app/Site/FormRepository.php#L267)). [ContentTypeRepository.php:207](cms/app/Content/ContentTypeRepository.php#L207) derives `allow_submissions` from existing forms. [ContentTypeEditView.vue:590](web/src/views/ContentTypeEditView.vue#L590) keeps their texts until the type's settings are edited. [Admin/SiteController.php](cms/app/Controllers/Admin/SiteController.php) and [cms/index.php](cms/index.php) still expose standalone form create/update/delete endpoints; `FormRepository::create()` can therefore still write this format.
- **Safe to remove:** after migrating each form's settings into its type and its placements in designs and revisions to the type-based form, and converting or retiring old backups. Retire or redirect the standalone form write endpoints so the old format cannot be recreated. Multiple standalone forms can target one type with different settings; define how to preserve those differences before consolidating them.

## Content

### API exposure from `visibility`

- **Handles:** stored schemas from before the API opt-in. Their `api_enabled` is derived from the presence of `visibility`, and private types stay protected.
- **Where:** [ContentTypeRepository.php:213](cms/app/Content/ContentTypeRepository.php#L213) and [ContentTypeEditView.vue:570](web/src/views/ContentTypeEditView.vue#L570).
- **Safe to remove:** after writing `api_enabled` into every stored and configuration-defined schema, and converting or retiring old schema backups and callers. Preserve the existing private/public protection during conversion; deriving an explicit flag is not permission to make private content public.

### Pre-release entry shape (`data` bag, root `body`)

- **Handles:** entries that kept fields in a raw `data` object and a `body` at the root.
- **Where:** [ContentRepository.php:818](cms/app/Content/ContentRepository.php#L818) drops the leftovers on normalize. `revisionFieldSource()` in [ContentEditView.vue:1380](web/src/views/ContentEditView.vue#L1380) still reads `data` from old revisions.
- **Safe to remove:** once no pre-release entries or revisions remain, old backups have been converted or retired, and callers no longer submit the old shape. Keep schema-defined fields named `data` or `body` intact.

### Entries without immutable `uid`

- **Handles:** entries written without `uid`. The backend derives a 12-character identifier from collection, creation time and id; the website link picker falls back to slug/id.
- **Where:** [ContentRepository.php](cms/app/Content/ContentRepository.php) `stableId()`, `findByIdentifier()`, `normalizeAndValidate()` and `restoreRevision()`. [siteLinks.js](web/src/composables/siteLinks.js) `entryIdentifier()` and `destinationAvailable()` retain slug/id handling; [siteLinks.test.js](web/src/composables/siteLinks.test.js) explicitly covers legacy entries.
- **Safe to remove:** after backfilling immutable ids into entries (including deleted content and restorable snapshots) and rewriting persisted references such as homepage settings and menu/layout links. Preserve identifiers already issued by `stableId()`, or provide aliases: an ordinary save currently assigns a random `uid` to an entry that lacks one. Old backups and callers must be converted or retired too.
- **Streamlining:** use `uid` consistently when storing website references. Keep slug/id lookup for routes and API operations that still intentionally accept those identifiers; it is broader than this fallback.

## Accounts, media, workspaces

### API tokens inside user records

- **Handles:** tokens stored in `users/{id}.json` under `api_tokens`. They move to `api-tokens/` and keep a `legacy_user_id`.
- **Where:** [ApiTokenRepository::migrateLegacyUserTokens()](cms/app/Auth/ApiTokenRepository.php#L123), called from `all()`, `find()` and `findByToken()`. It scans users and removes `api_tokens` after copying missing tokens. `legacy_user_id` is written but has no consumer in this repository.
- **Safe to remove:** once the token migration has actually run successfully on every installation and old user records cannot be reintroduced. Merely starting the release is insufficient. The current `restoreUsers()` strips embedded tokens rather than migrating them; `restoreApiTokens()` reads `tokens.json`, so do not assume old users-only backups restore those tokens.
- **Streamlining:** remove the user scans and the token repository's `UserRepository` dependency. Stop writing `legacy_user_id`; remove existing copies only if no external tooling needs the provenance.

### Singular permission keys and encoded token permissions

- **Handles:** grants using `action` / `resource` instead of `actions` / `resources`, including scalar values cast to lists. Token permissions can also arrive as a JSON-encoded string. Roles can be read/restored with `name` as the fallback for a missing `id`.
- **Where:** `normalizePermissions()` in [ApiTokenRepository.php](cms/app/Auth/ApiTokenRepository.php) and [RoleRepository.php](cms/app/Auth/RoleRepository.php), `normalizeGrant()` / `capabilities()` in [PermissionService.php](cms/app/Auth/PermissionService.php), and the role id fallback in [RestoreService::restoreRoles()](cms/app/Backups/RestoreService.php). [PermissionServiceTest.php](tests/php/PermissionServiceTest.php) still passes a singular grant to `firstUncovered()`.
- **Safe to remove:** after canonicalizing stored roles and tokens and supported backups, and moving callers to plural list keys and explicit role ids. These forms are still accepted inputs; removing them requires retiring that input contract as well. Token save/authentication normalizes grants, while reading a role alone does not rewrite its JSON.
- **Streamlining:** share one grant normalizer between roles, tokens and permission evaluation, then retire aliases at the input boundary. Keep deny precedence, field restrictions and conditions unchanged. Decide separately whether JSON-string input is still needed by any direct repository caller.

### Unscoped permission resources for workspace requests

- **Handles:** `content:*`, `schema:*` and `media:*` grants alongside `workspace:{slug}:...` resources. Unscoped grants apply across workspaces.
- **Where:** [PermissionService.php](cms/app/Auth/PermissionService.php) `resourceCandidates()` and `coversPair()`. [WorkspaceSupportTest.php](tests/php/WorkspaceSupportTest.php) calls this a legacy fallback; [RoleRepository.php](cms/app/Auth/RoleRepository.php) still issues unscoped built-in grants today.
- **Safe to remove:** only after an explicit redesign of cross-workspace grants, built-in roles, token scopes, delegation checks and matching UI. This remains current behavior; migrating old records alone does not make removal safe. Expanding a grant to today's workspaces would change its access to future workspaces.
- **Streamlining:** keep a clear distinction between global and workspace-specific grants and reuse resource-matching rules. Removing duplicate resource candidates would first require equivalent semantics for allow, deny and delegation.

### Discarded per-user permissions

- **Handles:** a `permissions` property on user records. User authorization now comes from the assigned role; token authorization comes from the token's own grants.
- **Where:** [UserRepository::save()](cms/app/Auth/UserRepository.php) unsets the property; [RestoreService::restoreUsers()](cms/app/Backups/RestoreService.php) strips it again. [PermissionService::grants()](cms/app/Auth/PermissionService.php) ignores user-level grants.
- **Safe to remove:** the cleanup can go after all user records and supported backups have been cleaned and old writers retired. Keep the rule that user-supplied permissions never grant access. Do not migrate discarded user grants into roles without reviewing the resulting access.
- **Streamlining:** centralize user-record cleanup in `UserRepository::save()`; the duplicate `permissions` unset in restore can then go independently of the old-data retirement. Embedded-token handling still needs its own decision.

### Old thumbnail cache (`media-thumbs/`)

- **Handles:** the per-workspace thumbnail directory from before variants were stored per file.
- **Where:** [MediaRepository::removeLegacyThumbnailCache()](cms/app/Media/MediaRepository.php#L990), run on construction.
- **Safe to remove:** once cleanup has successfully run for every workspace, including archived ones, and `media-thumbs/` is absent. The constructor only cleans the workspace being opened. Note that `/media-thumbs` is still in `SiteRepository::RESERVED_PREFIXES`.

### Workspace icons in `workspaces/icons/`

- **Handles:** icons written before they lived inside each workspace.
- **Where:** [WorkspaceRepository.php:326](cms/app/Workspaces/WorkspaceRepository.php#L326).
- **Safe to remove:** once `iconPath()` has successfully migrated every old icon and the old directory has no needed files. Merely starting the release is insufficient: non-PNG icons need GD, and read/write failures leave the source in place. Include filesystem backups that can reintroduce the old directory in the retirement decision.

## Streamlining opportunities

These are follow-up changes, not migrations implemented by this document update.

| Opportunity | What it simplifies | Prerequisite |
| --- | --- | --- |
| Add an explicit, repeatable migration command with a dry run and per-workspace results | Moves compatibility work out of constructors and ordinary reads; reports skipped or failed files instead of assuming an update completed it | Cover archived workspaces, preserve file times/ids, and mark completion only after successful writes. Keep restore-time conversion while old backups are supported. |
| Batch the small design conversions | Removes string-border, background-prop, boolean-sticky, density and radius branches from routine normalization | Rewrite current designs and revision snapshots, then convert or retire old backup/client inputs. Use the existing normalization rules only for compatible formats; do not relabel format-1 trees as format 2. |
| Finish theme-folder and component-id retirement as one change | Removes migration locks/scans, alias lookups, reserved id patterns and restore path remapping | Verify every workspace and revision, expire stale clients, and settle the old-backup support window. |
| Consolidate forms and collection cards | Removes two storage/rendering paths and repeated legacy-form scans during schema reads | Implement migrations first; preserve card appearance, component placements and differing settings for forms sharing a type. |
| Materialize schema flags and immutable entry ids | Removes inferred API/submission flags and legacy identifier generation | Preserve private access and existing references; audit config schemas, deleted content, revisions and backups too. |
| Share permission normalization and user cleanup | Removes repeated grant conversion and redundant restore cleanup | Keep existing access decisions and accepted inputs until callers have migrated; retain global workspace grants while built-in roles depend on them. |
| Retire disposable cache cleanup and completed icon migration | Removes filesystem work from media construction and workspace listing | Verify cleanup/migration succeeded in every workspace. Removing `/media-thumbs` from reserved routes is a separate URL-policy decision. |

Start with shared normalization and duplicate cleanup, which can preserve today's behavior. The larger reductions need migration tooling and a declared minimum supported data/backup version. The backup manifest already records the CMS version, but [RestoreService::restore()](cms/app/Backups/RestoreService.php) currently only requires the manifest to exist; it does not enforce a minimum supported version.

## Keep as current behavior

Do not classify every fallback or migration helper as removable legacy code:

- Layout format checks protect against future incompatible designs as well as old ones.
- [ContentRepository.php](cms/app/Content/ContentRepository.php) `migrateFields()`, `seedDefaultLocaleFromRoot()` and `syncLocalization()` support field renames and localization changes that users can make today.
- [ContentTypeRepository.php](cms/app/Content/ContentTypeRepository.php) supports configuration-defined schemas and schema-less content directories; [WorkspaceRepository.php](cms/app/Workspaces/WorkspaceRepository.php) reconciles folder changes with settings. Retiring those paths would change supported recovery/configuration behavior.
- [Auth::allows()](cms/app/Auth/Auth.php) still gates custom CSS editing and theme revision access in [Admin/SiteController.php](cms/app/Controllers/Admin/SiteController.php). The role hierarchy cannot be deleted just because action-based permissions also exist. Likewise, supplying newly added built-in permissions to the locked admin role remains an ongoing update requirement.
- Default values, locale fallbacks, responsive inheritance and unavailable-reference handling remain part of the current data model. Vendor deprecations in Parsedown belong to dependency maintenance, not application data migration.
