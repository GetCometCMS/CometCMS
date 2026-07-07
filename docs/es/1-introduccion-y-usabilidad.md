# 1. Introducción y Usabilidad

## ¿Qué es CometCMS?

CometCMS es un Sistema de Gestión de Contenidos (CMS) "headless" ligero diseñado para ejecutarse en cualquier alojamiento PHP compartido estándar. Su característica más distintiva es que **no requiere una base de datos tradicional** (como MySQL o PostgreSQL), ni Node.js, ni dependencias de Composer instaladas en el servidor, ni acceso por línea de comandos (SSH).

Todo el contenido se almacena como archivos JSON estáticos en el directorio `storage/`. Esto hace que las copias de seguridad, migraciones y el control de versiones de contenido sean tan simples como copiar archivos.

## Usabilidad: Navegando por el Panel de Administración

El diseño de CometCMS está pensado para ser intencionalmente simple, centrado en ofrecer una experiencia de usuario (UX) limpia y directa. A continuación, explicamos cada apartado principal:

### Panel de Control (Dashboard)
`/admin`

Es la pantalla de inicio al iniciar sesión. Ofrece una vista rápida de la actividad reciente y accesos directos a las áreas más utilizadas. La usabilidad aquí se centra en minimizar los clics necesarios para comenzar a trabajar.

### Tipos de Contenido (Content Types)
`/admin/content/{collection}`

Aquí es donde defines la estructura de tus datos y gestionas las entradas de contenido.
- **Colecciones repetibles (Collections):** Útiles para blogs, noticias, productos. Puedes tener múltiples entradas.
- **Páginas únicas (Single Pages):** Ideales para páginas estáticas como "Inicio" o "Sobre nosotros".

**Mejora de Usabilidad:** Al diseñar tipos de contenido, utiliza nombres descriptivos para los campos (ej. "Imagen Destacada" en lugar de "img_1") y proporciona instrucciones claras en las descripciones de los campos para guiar a los editores.

### Biblioteca de Medios (Media)
`/admin/media`

Un gestor de archivos integrado. Permite subir, organizar en carpetas y gestionar imágenes y otros archivos que se utilizarán en el contenido.
- Puedes organizar medios por categorías.
- La búsqueda es rápida y visual.

### Gestión de Usuarios y Roles (Users & Roles)
`/admin/users`

Permite invitar a nuevos colaboradores.
- **Roles predeterminados:** `admin` (acceso total), `editor` (puede editar contenido y medios), `viewer` (solo lectura).
- Puedes crear roles personalizados con permisos granulares (por acción, tipo de contenido, etc.), asegurando que cada usuario solo vea y modifique lo que le corresponde, simplificando su interfaz.

### Tokens de API (API Tokens)
`/admin/api-tokens`

Esencial para la arquitectura "headless". Aquí generas claves (Bearer tokens) para que tus aplicaciones frontend (Next.js, Astro, etc.) o servicios de IA puedan leer o escribir contenido a través de la API REST de forma segura.

### Copias de Seguridad (Backups)
`/admin/backups`

Como no hay base de datos, hacer una copia de seguridad implica comprimir en un `.zip` el directorio `storage/`. Desde esta sección puedes crear y restaurar estas copias fácilmente con un clic.

### Webhooks
`/admin/webhooks`

Configura notificaciones para cuando cambie el contenido (ej. al publicar un artículo). Muy útil para disparar reconstrucciones en generadores de sitios estáticos (SSG).

## Consejos Generales de Usabilidad para Desarrolladores/Administradores

1. **Principio de Menor Privilegio:** Asigna a los editores solo los permisos que necesitan. Una interfaz con menos opciones confunde menos.
2. **Campos Requeridos Claros:** Si un campo es esencial para el frontend, márcalo como obligatorio en la configuración del Tipo de Contenido para evitar errores en la visualización.
3. **Localización (Idiomas):** CometCMS soporta múltiples idiomas. Si configuras varios `locales`, el panel mostrará pestañas para cada idioma, facilitando la traducción de entradas.
