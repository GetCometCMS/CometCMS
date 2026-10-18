# Content Entries

## Browsing entries

Select a collection from the **sidebar** to see a list of all entries in that collection. The list shows the title/slug, status, author, and last-updated date.

Single page content types appear under **Content** in the sidebar like collections. They open directly in the editor instead of showing a list.

## Creating an entry

1. Open a collection from the sidebar.
2. Click **New entry**.
3. Fill in the fields.
4. Click **Save**.

Entries are created with a `draft` status by default. Toggle the **Published** switch (or a `boolean` / `datetime` field of your choice) to publish them.

If the content type has locales, new entries start in the default locale unless you choose another locale. The entry slug, status, author, and publish date are shared across locales.

## Editing an entry

Click any row in the list to open the entry editor. Changes are saved when you click **Save entry**.

Localized entries show locale pills above the form. Solid pills have saved translations, dashed pills create a missing locale variant, and the default locale is labeled. Deleting a locale variant only removes that translation; the entry and other locales remain.

## Entry history

Every time you save an entry, a revision snapshot is stored. CometCMS keeps up to `content.max_revisions` snapshots per entry, which defaults to `50` in `config/config.php`. Set it to `0` to disable revision history, or a negative value to keep revisions indefinitely.

Open the **Entry history** side panel to:

- Browse all previous versions, including which user saved each one.
- See a **diff** of what changed between each revision.
- **Restore** any revision by clicking the restore icon — this loads the old values into the editor without saving. Review the changes and click **Save entry** to apply.

The top of the history list always shows the **current** state with a blue _(Current)_ badge.

## Deleting an entry

Click **Delete** on an entry to move it to the **Trash**. Trashed entries can be restored or permanently deleted from the Trash section.

Deleting a single page also moves its one entry to Trash. While it is trashed, the direct editor/API route returns not found for public reads. Restoring the trashed page brings it back at the same fixed slug, unless another active entry already exists for that single page content type.

## Entry metadata

Every entry automatically gets these system fields (not editable):

| Field        | Description                                          |
| ------------ | ---------------------------------------------------- |
| `id`         | Unique identifier (auto-generated).                  |
| `collection` | The collection this entry belongs to.                |
| `created_at` | ISO 8601 timestamp when the entry was first created. |
| `updated_at` | ISO 8601 timestamp of the last save.                 |
| `author_id`  | ID of the user who created the entry.                |
| `updated_by` | ID of the user who last updated the entry.           |
