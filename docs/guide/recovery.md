# Recuperación de Acceso de Administración

Si pierdes el acceso al panel de administración (contraseña olvidada, cuenta eliminada, etc.) y aún tienes **acceso FTP o SSH** al servidor, hay dos caminos de recuperación.

## Opción 1 — Eliminar todos los usuarios y volver a ejecutar la configuración (recomendado)

1. Conéctate a tu servidor vía FTP o SSH.
2. Elimina todos los archivos dentro de `cms/storage/users/`:

   ```bash
   rm cms/storage/users/*.json
   ```

   **Tu contenido está a salvo.** Solo las cuentas de usuario viven en `cms/storage/users/`. Las entradas de contenido, los medios y los tipos de contenido se almacenan en directorios separados y no se ven afectados.

3. Visita `https://tudominio.com/admin` en tu navegador. CometCMS detectará que no existen usuarios y mostrará la **pantalla de configuración** de primer inicio.

4. Crea una nueva cuenta de administrador.

## Opción 2 — Reemplazar el hash de la contraseña directamente

Si quieres preservar la cuenta de usuario existente, puedes restablecer la contraseña editando directamente el archivo JSON del usuario. Los tokens de API se almacenan de forma separada en `cms/storage/api-tokens/`.

1. Encuentra tu archivo de usuario en `cms/storage/users/`. Los archivos se nombran con el formato `{userId}.json`. Abre cada uno para encontrar el nombre de usuario correcto.

2. Genera un hash (cifrado) bcrypt para tu nueva contraseña. Cualquier factor de coste funciona — la función `password_verify()` de PHP lee el factor de coste directamente desde el hash.

   **Vía consola (CLI) de PHP:**

   ```bash
   php -r "echo password_hash('tuNuevaContrasena', PASSWORD_BCRYPT) . PHP_EOL;"
   ```

   **Vía un generador en línea** (ej. [bcrypt-generator.com](https://bcrypt-generator.com)) — el factor de coste mostrado por la herramienta no importa; cualquier hash bcrypt válido funcionará.

3. Abre el archivo JSON del usuario y reemplaza el valor del campo `password` con el nuevo hash:

   ```json
   {
     "id": "...",
     "username": "admin",
     "password": "$2y$12$tuNuevoHashAqui",
     "role": "admin",
     ...
   }
   ```

4. Guarda el archivo. Inicia sesión con tu nueva contraseña.

## Notas importantes

- Nunca expongas el directorio `cms/storage/` a la web pública. Tu servidor web solo debe servir archivos a través de `index.php`.
- Si generaste un hash bcrypt en una máquina no confiable o en un servicio en línea, vuelve a cambiar la contraseña desde el panel de administración una vez que hayas recuperado el acceso.