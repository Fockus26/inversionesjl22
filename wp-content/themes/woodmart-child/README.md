# README del Proyecto (Tema y funciones adicionales)

# Proyecto WooCommerce Personalizado

Este proyecto contiene las personalizaciones específicas para la tienda WooCommerce, incluyendo modificaciones en el tema (via `functions.php`) y el conjunto de funcionalidades y control de acceso que residen en la carpeta `inc/`.

## Estructura

- **functions.php**: Archivo principal del tema, donde se agregan hooks y filtros que modifican el comportamiento base de WooCommerce, WordPress y el tema.
- **inc/**: Carpeta con módulos PHP para funcionalidades específicas:
  - `roles-permissions/`: Gestión avanzada de roles, permisos, y estados de pedido personalizados.
  - `integrations/`: Conexiones con plugins externos como YITH.
  - `pdf-reports/`: Generación y control de reportes en PDF.
  - `order-status/`: Estados personalizados de pedidos y transiciones.
  - `ajax-actions.php`: Acciones AJAX para interacción en backend y frontend.
  - Otros helpers y configuraciones propias.

## Características destacadas

- Gestión granular de estados de pedido, con estados personalizados como `impreso`.
- Control de permisos por rol, limitando transiciones, visibilidad y acciones.
- Integración con sistema QR para escaneo y actualización de estados en tiempo real.
- Reportes PDF personalizados y optimizados para impresión térmica y documentos.
- Funciones específicas para roles `storage_manager` y `seo_manager`.
- Interacción AJAX para funcionalidades sin recarga.
- Plantillas para documentos de impresión en papel térmico de 80mm.

## Instalación y configuración

- Se asume instalación estándar de WordPress y WooCommerce.
- Incluir la carpeta `inc/` en el tema activo o como plugin.
- Incluir funciones del archivo `functions.php` del tema.
- Configurar roles personalizados y permisos según `roles-permissions/`.
- Ajustar estados y reportes a las necesidades particulares.

## Desarrollo y mantenimiento

- El proyecto está modularizado para facilitar mantenimiento y escalabilidad.
- Utiliza APIs de WooCommerce para estados y permisos.
- AJAX para operaciones en tiempo real (escaneo QR, notificaciones).
- Estilos y plantillas preparados para impresión térmica.
- Documentación inline en los archivos para facilitar modificaciones.
