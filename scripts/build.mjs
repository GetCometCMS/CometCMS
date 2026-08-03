import { cpSync, existsSync, mkdirSync, rmSync, writeFileSync } from "node:fs";
import { spawnSync } from "node:child_process";
import { dirname, join, parse, relative, resolve, sep } from "node:path";
import { fileURLToPath } from "node:url";

const projectRoot = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const mode = process.argv[2] || "full";
const distDir = resolve(projectRoot, process.argv[3] || process.env.DIST || "dist");

function isInside(parent, child) {
  const path = relative(parent, child);
  return path !== "" && path !== ".." && !path.startsWith(`..${sep}`);
}

const protectedPaths = ["cms", "web", "tests", "scripts"].map((path) => join(projectRoot, path));
if (
  distDir === projectRoot
  || distDir === parse(distDir).root
  || isInside(distDir, projectRoot)
  || protectedPaths.some((path) => distDir === path || isInside(path, distDir))
) {
  throw new Error(`Refusing to use unsafe distribution directory: ${distDir}`);
}

function remove(path) {
  rmSync(path, { recursive: true, force: true });
}

function runAdminBuild() {
  const executable = process.platform === "win32" ? (process.env.ComSpec || "cmd.exe") : "npm";
  const args = process.platform === "win32"
    ? ["/d", "/s", "/c", "npm --workspace web run build"]
    : ["--workspace", "web", "run", "build"];
  const result = spawnSync(executable, args, {
    cwd: projectRoot,
    env: { ...process.env, COMET_ADMIN_OUT_DIR: join(distDir, "admin") },
    stdio: "inherit",
  });
  if (result.error) throw result.error;
  if (result.status !== 0) process.exit(result.status ?? 1);
}

function buildAdmin() {
  remove(join(distDir, "admin"));
  runAdminBuild();
}

function buildFullDistribution() {
  remove(distDir);
  mkdirSync(distDir, { recursive: true });

  for (const file of ["index.php", "router.php", ".htaccess"]) {
    cpSync(join(projectRoot, "cms", file), join(distDir, file));
  }
  for (const directory of ["app", "config"]) {
    cpSync(join(projectRoot, "cms", directory), join(distDir, directory), { recursive: true });
  }

  runAdminBuild();

  const storageDir = join(distDir, "storage");
  const storageProtection = join(projectRoot, "cms", "storage", ".htaccess");
  mkdirSync(storageDir, { recursive: true });
  if (existsSync(storageProtection)) {
    cpSync(storageProtection, join(storageDir, ".htaccess"));
  } else {
    writeFileSync(
      join(storageDir, ".htaccess"),
      "Options -Indexes\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n",
    );
  }

  for (const directory of [
    "sessions", "users", "roles", "api-tokens", "logs", "backups",
    "updates", "cache", "cache/login-throttle", "workspaces",
  ]) {
    const target = join(storageDir, directory);
    mkdirSync(target, { recursive: true });
    writeFileSync(join(target, ".gitkeep"), "");
  }

  console.log(`Built ${distDir}. Upload that folder's contents to your server.`);
}

if (mode === "clean") {
  remove(distDir);
  remove(join(projectRoot, "cms", ".vite-hot"));
  remove(join(projectRoot, "cms", "admin"));
} else if (mode === "admin") {
  buildAdmin();
} else if (mode === "full") {
  buildFullDistribution();
} else {
  throw new Error(`Unknown build mode: ${mode}`);
}
