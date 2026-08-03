import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { existsSync, writeFileSync, unlinkSync } from 'fs'
import { resolve } from 'path'

const projectRoot = resolve(__dirname, '..')
const outDir = process.env.COMET_ADMIN_OUT_DIR
  ? resolve(process.env.COMET_ADMIN_OUT_DIR)
  : resolve(projectRoot, 'dist/admin')
const hotFile = resolve(projectRoot, 'cms/.vite-hot')

export default defineConfig({
  base: '/admin/',
  plugins: [
    vue(),
    // Write/clean "hot" file so PHP knows when the dev server is running.
    {
      name: 'comet-hot-file',
      configureServer(server) {
        server.httpServer?.once('listening', () => {
          const addr = server.httpServer?.address()
          const port = typeof addr === 'object' && addr ? addr.port : 5173
          writeFileSync(hotFile, `http://localhost:${port}`)
        })
        const cleanup = () => {
          if (existsSync(hotFile)) unlinkSync(hotFile)
        }
        process.on('exit', cleanup)
        process.on('SIGINT', () => { cleanup(); process.exit() })
        process.on('SIGTERM', () => { cleanup(); process.exit() })
      },
    },
  ],
  root: __dirname,
  build: {
    outDir,
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: resolve(__dirname, 'src/main.js'),
    },
  },
  server: {
    // Proxy API calls to the PHP dev server during development.
    proxy: {
      '/admin/api': 'http://localhost:8000',
      '/api': 'http://localhost:8000',
      '/media': 'http://localhost:8000',
    },
    cors: true,
  },
})
