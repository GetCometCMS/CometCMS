# Instalación

## Requisitos

**Servidor (producción):**
- PHP 8.1 o posterior (con extensiones `json`, `mbstring`, `fileinfo`)
- Un servidor web (Apache, Nginx, Caddy)

**Máquina de desarrollo:**
- Node.js 18+ (solo necesario para compilar el frontend de administración — no requerido en el servidor)

## Despliegue

### 1. Clonar el repositorio

```bash
git clone https://github.com/your-org/cometcms.git
cd cometcms
```

### 2. Construir el paquete de despliegue

```bash
make build
```

Esto compila el frontend de administración en Vue y ensambla todo en una carpeta `dist/`. El resultado es una aplicación PHP independiente — no se necesita Node.js en el servidor.

### 3. Subir al servidor

Sube el **contenido** de `dist/` a la raíz web de tu servidor (o a un subdirectorio). La estructura se verá así:

```
index.php
router.php
app/
config/
admin/        ← frontend compilado de Vue
storage/      ← directorio vacío con permisos de escritura para contenido/usuarios/etc.
```

### 4. Configurar el servidor web

Todas las peticiones deben ser enrutadas a través de `index.php`.

**Apache** (`.htaccess` se incluye en la construcción):
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [QSA,L]
```

**Nginx:**
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### 5. Asegurar permisos de escritura en `storage/`

```bash
chmod -R 755 storage/
```

### 6. Abrir la administración

Navega a `https://tudominio.com/admin`. Aparecerá la pantalla de configuración de primer inicio.

---

## Configuración (opcional)

`config/config.php` te permite ajustar:
- `app.timezone` — por defecto `UTC`
- `content.max_revisions` — instantáneas de revisión mantenidas por entrada, por defecto `50`
- `cache.ttl` — TTL (tiempo de vida) del caché de la API en segundos
- `security.login_throttle` — límites de protección contra fuerza bruta
- `updates.repository_url` — repositorio de GitHub usado por la página de actualización
- `updates.require_checksum` — requiere un archivo `.sha256` del release antes de instalar
- `updates.preserved_paths` — rutas omitidas al instalar ZIPs de actualización
- `webhooks` — URLs de webhooks salientes

La URL se **autodetecta** de la petición HTTP — no se necesita configurar un `base_url`.

La página de actualización de la administración se abre al hacer clic en la versión de CometCMS en la barra lateral. Las comprobaciones de actualización usan los releases de GitHub. Las actualizaciones se descargan y verifican primero, y luego se instalan desde el paquete preparado. Al instalar una actualización, se reemplazan los archivos de la aplicación mientras se preservan las rutas configuradas, incluyendo `storage/` (contenido, tipos de contenido, medios, usuarios, revisiones y otros datos locales).

CometCMS soporta releases públicos de GitHub para verificar y descargar actualizaciones.

---

## Desarrollo local

```bash
npm install
make dev
```

Esto inicia simultáneamente el servidor integrado de PHP y el servidor de desarrollo de Vite. El panel de administración estará disponible en `http://localhost:8000/admin`.