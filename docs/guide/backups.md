# Copia de Seguridad y Restauración (Backup & Restore)

CometCMS incluye un sistema integrado de copia de seguridad y restauración para guardar los datos del CMS en archivos ZIP y restaurar partes seleccionadas posteriormente.

## Almacenamiento de copias de seguridad

Las copias de seguridad (backups) se almacenan en:

```bash
cms/storage/backups/
```

El panel de administración puede listar, inspeccionar, descargar, eliminar, subir y restaurar copias de seguridad desde esta carpeta.

![Pantalla de copia de seguridad y restauración en la administración de CometCMS](../screenshots/view-backups.png)

## Qué se puede respaldar

Al crear una copia de seguridad, puedes elegir qué partes incluir:

| Parte | Por defecto | Detalles |
| --- | --- | --- |
| Tipos de contenido | Sí | Esquemas de colecciones y campos |
| Entradas | Sí | Entradas y el historial de revisiones |
| Medios (Media) | Sí | Archivos subidos, categorías y metadatos |
| Webhooks | Sí | URLs de webhooks salientes, secretos y eventos |
| Usuarios | No | Cuentas de usuario, roles y tokens de API de la aplicación |

> **Contraseñas y tokens de API:** Por defecto, los hashes (cifrados) de las contraseñas y de los tokens de API se eliminan de las copias de seguridad. Para incluirlos y permitir una restauración completa de cuentas y tokens, establece `'include_password_hashes' => true` bajo `'backups'` en el archivo `cms/config/config.php`.

## Restaurar

Antes de restaurar, CometCMS inspecciona el ZIP y muestra el número de tipos de contenido, entradas, revisiones, archivos multimedia, usuarios, roles y webhooks que contiene. Luego, tú eliges cuáles de las partes disponibles quieres restaurar.

La restauración de usuarios está desactivada por defecto de forma intencional para evitar sobrescribir accidentalmente cuentas en el servidor de destino. La restauración de usuarios requiere una copia de seguridad creada con los hashes de las contraseñas incluidos; de lo contrario, los usuarios serán omitidos porque no podrían iniciar sesión.

## Flujo de trabajo en la administración

1. Ve a **Copia de seguridad / Restaurar (Backup / Restore)** en el panel de administración.
2. Crea una nueva copia de seguridad o sube un archivo ZIP de copia de seguridad existente.
3. Inspecciona la vista previa de la copia de seguridad.
4. Selecciona las partes a restaurar.
5. Elige si deseas sobrescribir los archivos y registros existentes.
6. Haz clic en **Restaurar partes seleccionadas (Restore selected parts)**.

## Copia de seguridad completa del sistema de archivos

Para una copia de seguridad completa del lado del servidor, copia todo el directorio `storage/` vía FTP o SSH:

```bash
cp -r cms/storage/ /ruta/hacia/tu/backup/storage-$(date +%Y%m%d)/
```

Para restaurar, vuelve a copiar la carpeta respaldada en `cms/storage/`.