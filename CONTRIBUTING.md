# Contributing to CometCMS

## Local Development

PHP 8.2+ and Node 24 LTS must be installed locally. No Composer or database needed.
The Node version is pinned in `.nvmrc`, which is also used by the CI, release, and documentation workflows. If you use a Node version manager, select that version before installing dependencies.
I'm primarily a JavaScript developer so the setup is done with npm scripts and Makefile commands, but the PHP code is vanilla and does not require a local server to run tests or build.

```bash
npm install   # install workspace dependencies
make dev      # start PHP + Vite together
```

Open `http://localhost:8000/admin`. The first visit shows the setup screen.

<p align="center">
  <img src="docs/screenshots/first-login.png" alt="CometCMS setup screen for the first admin account" width="860" />
</p>

## Build for production

```bash
make build
```

This assembles a deployment-ready `dist/` folder containing the PHP CMS, compiled admin UI in `dist/admin/`, config, rewrite files, and clean storage placeholders. Upload the contents of `dist/` to your server; production does not need Node.

## Website fonts

Website font files are not shipped in the CMS. The admin's website starter downloads Inter and Lora through the normal catalogue installer and stores them inside the new workspace as removable installed fonts. Empty workspaces use system fonts only. A download failure leaves the website working with system fonts; users can install families later in Manage fonts.

After a production build, `node tests/browser/typography.mjs` checks the font manager, uploads, live previews and a real catalogue installation using PHP and Chrome. It uses an isolated temporary installation. Add `--offline` to skip online catalogue access. The browser test needs Node 22+ and Chrome/Chromium (`CHROME_BIN` may override the executable).

The small font fixtures and their licences under `tests/fixtures/fonts/` are used only by tests and are excluded from production builds. They cover variable weights and static italics without requiring a network connection.

## Testing

The builder guide includes four short recordings of the real editor. To regenerate
the MP4 videos and WebP posters in `web/src/assets/builder-guide/`, install PHP,
FFmpeg and the Playwright browser dependencies, then run:

```bash
npx playwright install chromium ffmpeg
npm run record:builder-guide
```

Set `CHROME_BIN=/path/to/chrome` to use an existing Chrome installation. The
recorder builds and sets up a temporary CMS, demonstrates adding elements,
inline editing, device previews, and saving with history, then removes the
temporary installation. The videos are silent and use the English interface;
the guide provides translated descriptions and playback controls.

Run the full local test suite before shipping:

```bash
make test
```

That command lints PHP and Vue files, runs the Composer-free backend tests in `tests/php`, and runs the Vue/Vite unit tests with Vitest. For the same verification CI performs before a release build, run:

```bash
make ci
```

You can also run the layers independently:

```bash
make test-backend
make test-frontend
npm --workspace web run test:watch
```

Run `node tests/browser/link-modal.mjs` with PHP and Chrome/Chromium installed to check internal link modals, external links, keyboard and focus behavior, mobile sizing, previews and root/subdirectory installs under the real content security policy. Set `CHROME_BIN` to override the browser executable. The test uses an isolated temporary installation.

After `npm run build`, add `--builder` to also check the **Modal** template in the builder's header dropdown, its content placeholder, editing and saving its height, reloading, full-page previews and the resulting live modal.

## Local reset

To wipe all data and return to the setup screen, just delete the storage folder. If you want to keep users and roles but reset content, media, sessions, logs, and settings, run:

```bash
rm -f cms/storage/users/*.json
rm -f cms/storage/roles/*.json
rm -rf cms/storage/workspaces/*
rm -f cms/storage/sessions/*
rm -f cms/storage/logs/*.log
rm -f cms/storage/backups/*.zip
rm -rf cms/storage/updates/*
rm -f cms/storage/settings.json
```

## Pull Requests

1. Ensure your fork is up to date with the `main` branch.
2. Run `make ci` to verify everything passes before opening a PR.
3. Write a clear PR description explaining what your changes do and why.
4. Keep changes focused — one feature or fix per pull request.

## Reporting Issues

Found a bug or have a feature request? Open an issue on the [GitHub repository](https://github.com/GetCometCMS/CometCMS/issues). Please include:

- A clear, descriptive title.
- Steps to reproduce the issue (if applicable).
- Expected vs. actual behavior.
- Screenshots or error logs where helpful.

## Code Style

- **PHP:** PSR-12 inspired. The test suite includes a lint step that validates basic formatting.
- **Vue / JavaScript:** ESLint via the `web` workspace. Run `npm --workspace web run lint` before committing.
- Keep it simple. CometCMS intentionally avoids frameworks, compilers, and complex abstractions on the PHP side.
