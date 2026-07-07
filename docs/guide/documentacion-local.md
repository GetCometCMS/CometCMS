# Documentación Local (VitePress)

La documentación de CometCMS está construida con [VitePress](https://vitepress.dev/), un generador de sitios estáticos ligero impulsado por Vue.

Si deseas modificar esta documentación, agregar nuevas guías o simplemente probar cómo se ve localmente antes de desplegar, puedes hacerlo fácilmente.

## Requisitos previos

Debes tener **Node.js 18+** instalado en tu máquina.

## Vista previa local (Modo desarrollo)

1. Abre una terminal y navega a la carpeta de la documentación:
   ```bash
   cd docs
   ```
2. Instala las dependencias (solo la primera vez):
   ```bash
   npm install
   ```
3. Inicia el servidor de desarrollo de VitePress:
   ```bash
   npm run dev
   ```
4. VitePress iniciará un servidor local (usualmente en `http://localhost:5173`). Abre esa URL en tu navegador.
5. Cualquier cambio que hagas en los archivos `.md` de la carpeta `docs/` se reflejará instantáneamente en el navegador (Hot Module Replacement).

## Exportar el producto final (Build)

Cuando estés listo para exportar la documentación como un sitio web estático final (archivos HTML, CSS, JS), ejecuta:

1. Asegúrate de estar en la carpeta `docs`:
   ```bash
   cd docs
   ```
2. Ejecuta el comando de compilación:
   ```bash
   npm run build
   ```
3. VitePress procesará todos los archivos `.md` y generará la versión estática de la documentación dentro de la carpeta oculta `docs/.vitepress/dist/`.
4. El contenido de esa carpeta `dist/` es lo que debes subir a tu servicio de hosting (como GitHub Pages, Vercel, Netlify o cualquier servidor web estático).

## Probar la exportación final

Puedes previsualizar rápidamente el sitio ya exportado (build) usando:

```bash
cd docs
npm run preview
```

Esto levantará un servidor local rápido sirviendo los archivos desde `docs/.vitepress/dist/` para que compruebes que todo se generó correctamente.