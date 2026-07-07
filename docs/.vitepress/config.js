import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'CometCMS',
  description: 'Documentación para CometCMS — un CMS ligero basado en archivos.',
  base: '/CometCMS/',
  themeConfig: {
    logo: { light: '/cms-logo-black.png', dark: '/cms-logo-white.png' },
    siteTitle: false,
    nav: [
      { text: 'Guía', link: '/guide/introduction' },
      { text: 'Referencia API', link: '/api/public-api' },
    ],
    sidebar: [
      {
        text: 'Primeros Pasos',
        items: [
          { text: 'Introducción y Usabilidad', link: '/guide/introduction' },
          { text: 'Instalación', link: '/guide/installation' },
          { text: 'Primer Inicio de Sesión', link: '/guide/first-login' },
          { text: 'Documentación Local', link: '/guide/documentacion-local' },
        ],
      },
      {
        text: 'Espacios de Trabajo',
        items: [
          { text: 'Workspaces', link: '/guide/workspaces' },
        ],
      },
      {
        text: 'Contenido',
        items: [
          { text: 'Tipos de Contenido', link: '/guide/content-types' },
          { text: 'Tipos de Campos', link: '/guide/field-types' },
          { text: 'Entradas de Contenido', link: '/guide/content-entries' },
          { text: 'Biblioteca de Medios', link: '/guide/media' },
        ],
      },
      {
        text: 'Usuarios y Acceso',
        items: [
          { text: 'Gestión de Usuarios', link: '/guide/users' },
          { text: 'Tokens de API', link: '/guide/api-tokens' },
        ],
      },
      {
        text: 'Importar y Exportar',
        items: [
          { text: 'Copias de Seguridad (Backups)', link: '/guide/backups' },
        ],
      },
      {
        text: 'Integraciones Avanzadas',
        items: [
          { text: 'Webhooks', link: '/guide/webhooks' },
          { text: 'Manejo con Inteligencia Artificial', link: '/guide/ai-integration' },
        ],
      },
      {
        text: 'Recuperación y Accesibilidad',
        items: [
          { text: 'Accesibilidad', link: '/guide/accessibility' },
          { text: 'Recuperar Acceso de Admin', link: '/guide/recovery' },
        ],
      },
      {
        text: 'Referencia API',
        items: [
          { text: 'API Pública', link: '/api/public-api' },
          { text: 'API de Administración', link: '/api/admin-api' },
          { text: 'API MCP', link: '/api/mcp' },
          { text: 'OpenAPI', link: '/api/openapi' },
        ],
      },
    ],
    socialLinks: [],
    search: {
      provider: 'local',
      options: {
        locales: {
          root: {
            translations: {
              button: {
                buttonText: 'Buscar',
                buttonAriaLabel: 'Buscar'
              },
              modal: {
                noResultsText: 'No se encontraron resultados para',
                resetButtonTitle: 'Borrar búsqueda',
                footer: {
                  selectText: 'para seleccionar',
                  navigateText: 'para navegar',
                  closeText: 'para cerrar'
                }
              }
            }
          }
        }
      }
    },
    footer: {
      message: 'Documentación de CometCMS',
    },
  },
})
