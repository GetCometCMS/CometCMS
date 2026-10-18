# First Login

## Setup screen

When you visit `/admin` for the first time (or after a [user reset](./recovery)), CometCMS shows the **setup screen** instead of the login page.

Enter a username and password (minimum 8 characters), then choose the first workspace name (optional custom slug).

Submitting setup creates:

- The initial admin account.
- The initial workspace.
- The default workspace assignment (used as the admin workspace fallback when `X-Comet-Workspace` is not set).

This admin account is the only account that can access the panel until more users are added.

![CometCMS setup screen for creating the first admin account](../screenshots/first-login.png)

## Admin URL

The admin panel is always available at:

```
https://yourdomain.com/admin
```

## Logging in

Enter your username and password. Sessions are server-side (PHP sessions stored in `cms/storage/sessions/`).

After login, you can add more workspaces in **Administration → Workspaces**. See [Workspaces](./workspaces) for routing, isolation, and permission scoping details.

## Finding your way around

The sidebar groups the admin into **Content** (your content types and media), **Website** (the Page Builder, once enabled) and **Administration** (content types, users and roles, workspaces, API & integrations, backups and updates).

Press <kbd>Ctrl</kbd> + <kbd>K</kbd> (<kbd>⌘</kbd> + <kbd>K</kbd> on macOS), or click **Search** in the sidebar, to jump to any page, find entries by title, or start a new entry.

## Your profile

Click your **name or avatar in the bottom-left corner of the sidebar** and choose **Profile** to open your profile page where you can:

- Upload or remove your profile picture
- Change your display name and email address
- Set a new password (requires entering your current password first)
