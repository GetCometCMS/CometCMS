import { readdirSync } from "node:fs";
import { spawnSync } from "node:child_process";
import { dirname, extname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const projectRoot = resolve(dirname(fileURLToPath(import.meta.url)), "..");

function phpFiles(directory) {
  return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const path = join(directory, entry.name);
    if (entry.isDirectory()) return phpFiles(path);
    return extname(entry.name) === ".php" ? [path] : [];
  });
}

for (const file of [...phpFiles(join(projectRoot, "cms")), ...phpFiles(join(projectRoot, "tests", "php"))]) {
  const result = spawnSync("php", ["-l", file], { cwd: projectRoot, stdio: "inherit" });
  if (result.error) throw result.error;
  if (result.status !== 0) process.exit(result.status ?? 1);
}
