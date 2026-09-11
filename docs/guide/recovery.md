# Admin Lockout Recovery

If you lose access to the admin panel (forgotten password, deleted account, etc.) and still have **FTP or SSH access** to the server, there are two recovery paths.

## Option 1 — Delete all users and re-run setup (recommended)

1. Connect to your server via FTP or SSH.
2. Delete all files inside `cms/storage/users/`:

   ```bash
   rm cms/storage/users/*.json
   ```

   **Your content is safe.** Only user accounts live in `cms/storage/users/`. Content entries, media, and content types are stored in separate directories and are not affected.

3. Visit `https://yourdomain.com/admin` in your browser. CometCMS will detect that no users exist and show the first-run **setup screen**.

4. Create a new admin account.

## Option 2 — Replace the password hash directly

If you want to preserve the existing user account, you can reset the password by editing the user's JSON file directly. Access tokens are stored separately in `cms/storage/api-tokens/`.

1. Find your user file in `cms/storage/users/`. Files are named `{userId}.json`. Open each one to find the right username.

2. Generate a bcrypt hash for your new password. Any cost factor works — PHP's `password_verify()` reads the cost factor from the hash automatically.

   **Via PHP CLI:**

   ```bash
   php -r "echo password_hash('yourNewPassword', PASSWORD_BCRYPT) . PHP_EOL;"
   ```

   **Via an online generator** (e.g. [bcrypt-generator.com](https://bcrypt-generator.com)) — the cost factor shown by the tool doesn't matter; any valid bcrypt hash will work.

3. Open the user JSON file and replace the `password` field value with the new hash:

   ```json
   {
     "id": "...",
     "username": "admin",
     "password": "$2y$12$yourNewHashHere",
     "role": "admin",
     ...
   }
   ```

4. Save the file. Log in with your new password.

## Important notes

- Never expose the `cms/storage/` directory to the public web. Your web server should only serve files through `index.php`.
- If you generated a bcrypt hash on an untrusted machine or online service, change the password again from within the admin panel once you regain access.
