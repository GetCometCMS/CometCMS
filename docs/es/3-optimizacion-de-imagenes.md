# 3. Implementación de un Optimizador de Imágenes

A menudo, los sitios web sufren problemas de rendimiento debido a imágenes no optimizadas subidas por los editores. Dado que CometCMS es un CMS basado en archivos de PHP, puedes integrar un optimizador de imágenes de un repositorio o biblioteca externa directamente en el flujo de subida de medios.

## Arquitectura de Medios en CometCMS

Para entender dónde enganchar el optimizador, es importante conocer el flujo de subida de archivos:

1. **Recepción HTTP:** La subida se inicia vía un endpoint de la API REST que es manejado por el controlador `MediaController.php` (típicamente `cms/app/Controllers/Admin/MediaController.php`).
2. **Lógica de Almacenamiento:** El controlador delega la escritura física del archivo y la gestión de metadatos al `MediaRepository.php` (`cms/app/Media/MediaRepository.php`).
3. **Persistencia:** Los archivos se guardan físicamente en `storage/workspaces/{workspace}/media/`.

## Estrategia de Integración del Optimizador

El mejor lugar para interceptar y optimizar una imagen es justo **antes** de que se guarde de forma definitiva en el `MediaRepository.php`.

### Paso 1: Elegir y Cargar la Biblioteca Optimizadora

Dado que CometCMS enfatiza una arquitectura sin dependencias pesadas ni Composer, tienes dos opciones:
1. **Usar extensiones PHP nativas (Recomendado):** Utilizar las extensiones `GD` o `Imagick` (que suelen estar instaladas en el 99% del hosting compartido) para redimensionar, comprimir y cambiar el formato (por ejemplo a WebP).
2. **Incluir una librería manual:** Si descargas un optimizador de otro repositorio de GitHub, colócalo en una carpeta (ej. `cms/app/Libs/Optimizer/`) y requiere los archivos manualmente con `require_once` en tu código.

### Paso 2: Interceptar en `MediaRepository` o `MediaController`

Supongamos que deseas convertir todas las subidas de imágenes (JPEG, PNG) al formato eficiente **WebP** y reducir su calidad para ahorrar espacio.

**Localizando el punto de intervención en `MediaRepository.php` (Pseudocódigo):**

```php
public function storeMedia($fileInfo, $workspace) {
    $tempPath = $fileInfo['tmp_name'];
    $mimeType = $fileInfo['type'];
    $originalName = $fileInfo['name'];

    // --> PUNTO DE INTEGRACIÓN: Optimización <--
    if (strpos($mimeType, 'image/') === 0 && $mimeType !== 'image/svg+xml') {
        $tempPath = $this->optimizeImage($tempPath, $mimeType);
        // Actualizamos la extensión a webp
        $originalName = pathinfo($originalName, PATHINFO_FILENAME) . '.webp';
    }

    // Código existente para mover $tempPath a storage/workspaces/{workspace}/media/...
}

private function optimizeImage($sourcePath, $mimeType) {
    // Ejemplo usando GD de PHP para optimizar
    $image = null;
    if ($mimeType === 'image/jpeg') {
        $image = imagecreatefromjpeg($sourcePath);
    } elseif ($mimeType === 'image/png') {
        $image = imagecreatefrompng($sourcePath);
    }

    if ($image) {
        $optimizedPath = $sourcePath . '_opt.webp';
        // Convertir a WebP con 80% de calidad
        imagewebp($image, $optimizedPath, 80);
        imagedestroy($image);
        return $optimizedPath;
    }
    return $sourcePath;
}
```

### Paso 3: Optimización Asíncrona (Avanzado)

Si la optimización consume mucho tiempo (como generar múltiples tamaños: thumbnail, medium, large), hacerlo en el hilo principal del request de PHP puede causar un "timeout".
En un hosting compartido sin colas de trabajos (jobs/workers), una solución es:
- Guardar la imagen original rápidamente.
- Lanzar una petición HTTP no bloqueante (o mediante `curl` con timeout de 1ms) a un endpoint interno oculto de tu CMS que realice la optimización en segundo plano.

### Consideraciones al Modificar el Core

CometCMS está diseñado para ser subido como una carpeta que simplemente sobreescribes cuando hay una actualización.
Si modificas los archivos del core (`cms/app/...`), recuerda que al hacer una actualización a través del panel de control (que reemplaza la carpeta `app/`), **tus cambios se sobrescribirán**.

**Recomendación:** Mantén un registro en git de tus optimizaciones o utiliza el sistema de hooks/eventos (si CometCMS llegara a implementarlos en un futuro) para mantener el código de optimización separado del core de la aplicación.