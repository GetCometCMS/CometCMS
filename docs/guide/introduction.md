# Introduction

CometCMS is a lightweight, **file-based headless CMS** built with PHP and a Vue 3 admin interface. It requires no database — all content, users, and settings are stored as plain JSON files on disk.

## Key concepts

| Concept           | Description                                                                                    |
| ----------------- | ---------------------------------------------------------------------------------------------- |
| **Content type**  | A schema that defines either a collection of entries (e.g. `posts`) or one single page-like entry (e.g. `start-page`). |
| **Content entry** | A saved item made up of the fields defined by its content type.                                |
| **Media**         | Uploaded images/files, managed through the media library.                                      |
| **User**          | A person who can log in to the admin panel. Users receive permissions through their assigned role. |
| **Access token**  | A bearer token that grants REST API and MCP access with specific permission grants.             |

## Roles

| Role     | Permissions                                                                       |
| -------- | --------------------------------------------------------------------------------- |
| `admin`  | Built-in full-access role. It cannot be deleted.                                  |
| `editor` | Built-in content and media editing role.                                          |
| `viewer` | Built-in read-oriented role.                                                      |

Roles can be customized or created from **Users → Edit user roles**.

## Storage layout

```
cms/storage/
  content/          # Content entries (one JSON file per entry)
  content-types/    # Content type schemas
  media/            # Uploaded media files
  media-meta/       # Media metadata
  revisions/        # Entry revision history
  sessions/         # PHP session files
  trash/            # Soft-deleted entries
  users/            # User accounts (one JSON file per user)
  roles/            # User role definitions and permission grants
  logs/             # Application logs
  backups/          # Saved backup ZIP files
```
