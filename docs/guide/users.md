# Gestión de Usuarios

## Roles de usuario

Los roles definen los permisos (grants) que reciben los usuarios. Un usuario tiene exactamente un rol, y cambiar el rol cambia los permisos efectivos del usuario.

| Rol integrado | Permisos por defecto |
| --- | --- |
| `admin` | Todos los permisos de sistema, tipos de contenido, contenido, medios, usuarios, tokens, roles, copias de seguridad, webhooks y actualizaciones. |
| `editor` | Panel de control, actividad, actualizaciones, perfil, lectura de esquemas, y escritura de contenido y medios. |
| `viewer` | Panel de control, actividad, actualizaciones, perfil, lectura de esquemas, lectura de contenido y lectura de medios. |

Usa **Editar roles de usuario (Edit user roles)** desde **Usuarios (Users)** para crear roles o cambiar sus permisos. El rol `admin` no puede ser eliminado.

![Editor de permisos de roles en la administración de CometCMS](../screenshots/view-user-role-permissions.png)

## Ver usuarios

Navega a **Usuarios (Users)** en la barra lateral. Los usuarios están agrupados por rol: Administradores → Editores → Espectadores.

## Crear un usuario

1. Haz clic en **Nuevo usuario (New user)**.
2. Introduce un nombre de usuario, contraseña (mínimo 8 caracteres) y rol.
3. Haz clic en **Crear (Create)**.

## Editar un usuario (solo para administradores)

Los administradores pueden hacer clic en el botón de **Editar (Edit)** en la tarjeta de cualquier otro usuario para actualizar su nombre visible, correo electrónico, rol o establecer una nueva contraseña. No puedes editar tu propia cuenta desde esta página — usa tu [página de perfil](#tu-perfil) en su lugar.

## Eliminar un usuario

Haz clic en **Eliminar (Delete)** en la tarjeta de un usuario. Esto solo elimina la cuenta del usuario — cualquier contenido que haya creado (`author_id`) **no** se ve afectado y permanece intacto.

## Tu perfil

Haz clic en tu **nombre o avatar en la esquina inferior izquierda de la barra lateral** para abrir tu página de perfil. Desde ahí puedes:

- Subir o eliminar tu foto de perfil.
- Actualizar tu nombre a mostrar y correo electrónico.
- Cambiar tu contraseña (se requiere la contraseña actual).