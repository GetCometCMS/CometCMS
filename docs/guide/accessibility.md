# 2. Mejoras e Implementación de Accesibilidad (a11y)

La accesibilidad web asegura que todas las personas, independientemente de sus capacidades físicas, cognitivas o tecnológicas, puedan interactuar y comprender tanto el panel de administración del CMS como el contenido que este distribuye.

## Accesibilidad en el Panel de Administración (Frontend Vue/Vite)

El panel de administración de CometCMS está construido con Vue. Para mejorar la accesibilidad de esta interfaz, los desarrolladores pueden enfocarse en las siguientes áreas:

1. **Navegación por Teclado:**
   - Asegurarse de que todos los elementos interactivos (botones, enlaces, menús desplegables) sean alcanzables usando la tecla `Tab`.
   - Utilizar directivas o atributos que garanticen que los modales (diálogos emergentes) atrapen el foco (Focus Trapping) para que los usuarios no naveguen por contenido oculto de fondo.

2. **Atributos ARIA (Accessible Rich Internet Applications):**
   - Utilizar roles ARIA (`role="alert"`, `role="navigation"`, `role="dialog"`) adecuadamente en componentes Vue personalizados.
   - Proveer etiquetas `aria-label` o `aria-labelledby` para controles de interfaz que no tengan texto visible (por ejemplo, botones de iconos).

3. **Contraste de Color:**
   - Revisar que el esquema de colores del panel cumpla con los estándares WCAG 2.1 AA (mínimo de ratio 4.5:1 para texto normal).

4. **Soporte para Lectores de Pantalla:**
   - Proporcionar texto oculto `.sr-only` para informar cambios dinámicos en la interfaz (como notificaciones de guardado exitoso) a los lectores de pantalla mediante regiones `aria-live`.

## Asegurar la Accesibilidad del Contenido (API REST)

Como CMS "headless", CometCMS provee los datos crudos a través de una API. La accesibilidad final depende del frontend que consume esta API, pero el CMS debe facilitar que el contenido sea accesible.

### 1. Obligatoriedad de Texto Alternativo (Alt Text) en Imágenes

Para garantizar que las imágenes sean accesibles para usuarios con discapacidad visual:
- Al definir un Tipo de Contenido (Content Type) que incluya un campo de tipo "Imagen" o "Medio", **siempre** asegúrate de añadir un campo de texto simple llamado `alt_text` asociado a esa imagen.
- Marca este campo `alt_text` como **Requerido (Required)** en la configuración del CMS. De esta manera, el editor no podrá publicar el contenido sin proporcionar una descripción válida.

Ejemplo en el diseño del esquema (JSON estructural):
```json
{
  "name": "imagen_destacada",
  "type": "media",
  "label": "Imagen Destacada"
},
{
  "name": "imagen_destacada_alt",
  "type": "text",
  "label": "Texto Alternativo (Alt)",
  "required": true,
  "description": "Describe la imagen para lectores de pantalla."
}
```

### 2. Estructura Semántica en Campos de Texto Enriquecido

Si utilizas campos de Texto Enriquecido (Rich Text) o Markdown, capacita a los editores para:
- Usar niveles de encabezado jerárquicos (`H2`, luego `H3`, sin saltarse niveles).
- Evitar usar negritas para simular títulos; usar la etiqueta semántica correspondiente.
- Asegurar que los enlaces tengan texto descriptivo ("Lee nuestro reporte de accesibilidad" en lugar de "Haz clic aquí").

### 3. Soporte Multi-idioma (Localización)

La accesibilidad también implica que el contenido esté en el idioma correcto.
- Al consumir la API (`?locale=es`), asegúrate de que tu aplicación frontend establezca el atributo `lang="es"` en la etiqueta `<html>` para que los lectores de pantalla utilicen la voz y pronunciación correctas.

---

Al implementar estas prácticas, garantizas que CometCMS sirva como una base sólida para crear sitios web y aplicaciones inclusivas y accesibles.