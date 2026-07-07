---
layout: home

hero:
  name: CometCMS
  text: El CMS que funciona en cualquier alojamiento PHP
  tagline: Basado en archivos, headless y sin dependencias. Sube un ZIP, abre /admin — y listo.
  image:
    light: /cms-logo-black.png
    dark: /cms-logo-white.png
    alt: CometCMS
  actions:
    - theme: brand
      text: Empezar
      link: /guide/introduction
    - theme: alt
      text: Documentación
      link: /guide/installation
    - theme: alt
      text: Descargar
      link: https://github.com/andreasjhagen/cometcms/releases/latest

features:
  - icon: 📁
    title: Almacenamiento basado en archivos
    details: El contenido se almacena como archivos JSON simples. No hay base de datos que configurar, migrar o mantener.
    link: /guide/content-types
    linkText: Tipos de contenido
  - icon: 🔌
    title: Headless y API-first
    details: Una API REST pública y limpia que permite consumir contenido desde cualquier frontend — Next.js, Astro, SvelteKit o un simple fetch.
    link: /api/public-api
    linkText: Referencia de API
  - icon: 🧩
    title: Tipos de campos flexibles
    details: Construye esquemas con texto, texto enriquecido, medios, relaciones, selectores, fechas y más.
    link: /guide/field-types
    linkText: Tipos de campos
  - icon: 🚀
    title: Cero dependencias en el servidor
    details: PHP 8.1+ es el único requisito para producción. Sin Composer, sin base de datos, sin CLI, sin SSH y sin Node.js en el servidor.
    link: /guide/installation
    linkText: Instalación
  - icon: 🔑
    title: Tokens de API con permisos granulares
    details: Permisos detallados por acción, tipo de contenido, categoría de medios y campo. Sin claves excesivamente amplias.
    link: /guide/api-tokens
    linkText: Tokens de API
  - icon: 💾
    title: Copias de seguridad integradas
    details: Crea y restaura copias de seguridad completas de todo el contenido, medios y configuraciones directamente desde el panel de administración.
    link: /guide/backups
    linkText: Copia de seguridad y restauración
---
