# Primer Inicio de Sesión

## Pantalla de configuración

Cuando visitas `/admin` por primera vez (o después de un [restablecimiento de usuario](./recovery)), CometCMS muestra la **pantalla de configuración** en lugar de la página de inicio de sesión.

Introduce un nombre de usuario y una contraseña (mínimo 8 caracteres), luego elige el nombre del primer espacio de trabajo o "workspace" (slug personalizado opcional).

Al enviar la configuración se crea:

- La cuenta de administrador inicial.
- El espacio de trabajo inicial.
- La asignación de espacio de trabajo por defecto (utilizada como respaldo del espacio de trabajo en la administración cuando `X-Comet-Workspace` no está definido).

Esta cuenta de administrador es la única que puede acceder al panel hasta que se añadan más usuarios.

![Pantalla de configuración de CometCMS para crear la primera cuenta de administrador](../screenshots/first-login.png)

## URL de Administración

El panel de administración siempre está disponible en:

```
https://tudominio.com/admin
```

## Iniciar sesión

Introduce tu nombre de usuario y contraseña. Las sesiones se manejan del lado del servidor (sesiones de PHP almacenadas en `cms/storage/sessions/`).

Después de iniciar sesión, puedes añadir más espacios de trabajo en **Configuración -> Workspaces**. Consulta la sección [Espacios de trabajo](./workspaces) para detalles sobre enrutamiento, aislamiento y alcance de permisos.

## Tu perfil

Haz clic en tu **nombre o avatar en la esquina inferior izquierda de la barra lateral** para abrir la página de tu perfil, donde podrás:

- Subir o eliminar tu foto de perfil
- Cambiar tu nombre a mostrar y dirección de correo electrónico
- Establecer una nueva contraseña (requiere ingresar tu contraseña actual primero)