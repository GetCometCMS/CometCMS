import { spawn } from "node:child_process";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const projectRoot = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const phpHost = process.argv[2] || process.env.PHP_HOST || "127.0.0.1";
const phpPort = process.argv[3] || process.env.PHP_PORT || "8000";
const viteHost = process.argv[4] || process.env.VITE_HOST || "127.0.0.1";
const vitePort = process.argv[5] || process.env.VITE_PORT || "5173";

const validPort = (port) => /^\d{1,5}$/.test(port) && Number(port) >= 1 && Number(port) <= 65535;
if (!/^[a-zA-Z0-9.:[\]-]+$/.test(viteHost) || !validPort(vitePort) || !validPort(phpPort)) {
  throw new Error("A development host or port contains an unsupported value.");
}
const npmExecutable = process.platform === "win32" ? (process.env.ComSpec || "cmd.exe") : "npm";
const npmArgs = process.platform === "win32"
  ? ["/d", "/s", "/c", `npm --workspace web run dev -- --host ${viteHost} --port ${vitePort}`]
  : ["--workspace", "web", "run", "dev", "--", "--host", viteHost, "--port", vitePort];

console.log(`CometCMS dev\n  PHP:  http://${phpHost}:${phpPort}/admin\n  Vite: http://${viteHost}:${vitePort}\n`);

const children = [
  spawn("php", ["-S", `${phpHost}:${phpPort}`, "-d", "upload_max_filesize=128M", "-d", "post_max_size=128M", "-t", "cms", "cms/router.php"], { cwd: projectRoot, stdio: "inherit" }),
  spawn(npmExecutable, npmArgs, { cwd: projectRoot, stdio: "inherit" }),
];

let stopping = false;
function stop(exitCode = 0) {
  if (stopping) return;
  stopping = true;
  for (const child of children) child.kill();
  process.exitCode = exitCode;
}

for (const signal of ["SIGINT", "SIGTERM"]) process.on(signal, () => stop());
for (const child of children) {
  child.on("error", (error) => {
    console.error(error.message);
    stop(1);
  });
  child.on("exit", (code) => {
    if (!stopping) stop(code ?? 1);
  });
}
