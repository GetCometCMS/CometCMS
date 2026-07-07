# 4. Plantillas Pre-ajustables (Presets) y Manejo con IA

CometCMS es inherentemente un CMS basado en archivos. Esta característica, junto con su API REST robusta, abre grandes posibilidades para crear "plantillas" pre-hechas y permitir que la Inteligencia Artificial gestione el contenido de manera autónoma.

## Carga de Plantillas Pre-ajustables (Presets)

En lugar de construir tipos de contenido y añadir entradas manualmente una por una a través del panel de administración, puedes "precargar" tu CMS con plantillas ya hechas.

### ¿Cómo funcionan los presets?
Dado que CometCMS no tiene base de datos, todo el esquema y los datos residen en la carpeta `storage/workspaces/{workspace}/`.

1. **Tipos de Contenido (Esquemas):**
   Se guardan en `storage/workspaces/{workspace}/content-types/{nombre}.json`.
2. **Entradas de Contenido:**
   Se guardan en `storage/workspaces/{workspace}/content/{colección}/{slug}.json`.

### Crear un Preset Empaquetado
Para crear una plantilla (ej. "Plantilla Blog Básico" o "Plantilla Portfolio"):
1. Configura el CMS localmente creando los Tipos de Contenido y añadiendo contenido de demostración.
2. Comprime el contenido de la carpeta `storage/workspaces/default/content-types/` y `storage/workspaces/default/content/` en un archivo `.zip`.
3. Para desplegar esta plantilla en un nuevo servidor, simplemente sube esos archivos JSON directamente a la estructura de carpetas correspondiente vía FTP. Automáticamente, el CMS reconocerá los esquemas y el contenido sin necesidad de migraciones de base de datos.

Esto facilita enormemente la creación de **"Temas" o "Starters"** que incluyan tanto el frontend (ej. un proyecto Next.js) como la estructura de datos que espera recibir (los JSONs de CometCMS).

## Manejo del CMS mediante Inteligencia Artificial (IA)

CometCMS expone una **API REST pública y protegida** (autenticada vía Bearer tokens) y cuenta con una arquitectura de datos muy predecible (JSON), lo que lo hace perfecto para ser operado por agentes de IA.

### Escenarios de Uso con IA

1. **Generación de Contenido Automatizada:**
   Una IA (como un script de Python usando OpenAI) puede escribir artículos. Usando la API de CometCMS, el script puede hacer una petición `POST` al endpoint:
   `POST /api/v1/workspaces/{workspace}/content/{collection}`
   Pasando el Token de API en los headers, la IA puede insertar el título, el cuerpo (en Markdown) y establecer el estado a `draft` (Borrador) para que un editor humano lo revise.

2. **Traducción Automática (Localización):**
   Un flujo de trabajo automatizado puede escuchar el webhook de `content.published`. Si el contenido está en español, el webhook envía el JSON a una IA. La IA traduce los campos de texto al inglés y luego hace un `PUT` a la API de CometCMS:
   `PUT /api/v1/workspaces/{workspace}/content/{collection}/{id}?locale=en`
   Esto actualiza el contenido localizado sin intervención humana.

3. **Interacción con el Servidor MCP Integrado:**
   CometCMS incluye un endpoint Streamable HTTP MCP (Model Context Protocol).
   `POST /mcp/v1/workspaces/{workspace}`
   Usando este protocolo con las credenciales de API adecuadas, un asistente de IA avanzado (como los que soportan herramientas MCP nativas) puede "descubrir" las herramientas del CMS, leer los esquemas, buscar entradas y modificar contenido, interactuando con el CMS como si fuera una base de conocimiento o sistema de registro (System of Record) viva.

### Configuración de Seguridad para la IA

Para que una IA opere el CMS de forma segura:
- Ve a **API-Tokens** en el panel de administración.
- Crea un token específico para la IA (ej. `IA_Generador_Articulos`).
- **Limita sus permisos:** Configura los permisos para que *solo* pueda crear o actualizar contenido en la colección `blog_posts`, restringiendo su acceso a usuarios, configuraciones, u otras colecciones críticas.
- Proporciona este token a tu script o agente de IA.