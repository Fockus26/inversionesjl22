# WooCommerce PDF Invoices & Packing Slips - Custom 80mm Version

Este plugin extiende WooCommerce PDF Invoices & Packing Slips para ofrecer una plantilla personalizada optimizada para impresión térmica en papel de 80mm, con funcionalidades adicionales para el manejo de estados de pedidos, QR dinámico y notificaciones en tiempo real.

## Características principales

- Plantillas PDF adaptadas para papel térmico de 80mm (facturas, comprobantes de envío, informes).
- Inclusión de código QR dinámico en facturas para escaneo y actualización rápida de estado.
- Personalización de datos de cliente (incluye campos extra como cédula y barrio).
- Integración con roles personalizados (`storage_manager`) para control de acceso y visualización.
- Notificaciones visuales en backend y frontend cuando se escanea el QR.
- Soporte para estados personalizados de pedidos (e.g. `impreso`).
- Estilos CSS optimizados para impresión térmica y legibilidad en papel pequeño.

## Archivos destacados

- **templates/Factura80mm/**: Plantillas HTML para facturas, packing slips e informes adaptados a 80mm.
- **template-functions.php**: Lógica para generación de QR, manejo AJAX para escaneo, validación y filtros personalizados.
- **style.css**: Estilos CSS específicos para impresión térmica con soporte de media queries para impresión.
- **pdf-generator.php, pdf-reports/**: Código para generación de PDFs y reportes personalizados.
- **roles-permissions/**: Control granular de permisos y roles para la gestión de estados y accesos.

## Requisitos

- WooCommerce instalado y activo.
- PHP 7.4+ recomendado.
- Acceso para instalar y activar plugins en WordPress.

## Instalación

1. Copia la carpeta del plugin en `wp-content/plugins/woocommerce-pdf-invoices-packing-slips`.
2. Activa el plugin desde el panel de administración de WordPress.
3. Configura WooCommerce PDF Invoices para usar la plantilla personalizada si es necesario.
4. Ajusta roles y permisos según las necesidades de tu equipo.

## Uso

- Las facturas incluyen un código QR que, al ser escaneado por usuarios con roles adecuados, actualiza el estado del pedido automáticamente.
- Notificaciones visuales indican el éxito o estado de la orden tras escaneo.
- Los informes y packing slips están optimizados para impresión en papel térmico de 80mm.

## Personalización

- Puedes modificar estilos en `style.css` para adaptar la impresión.
- Añade o modifica campos extra en las plantillas dentro de `templates/Factura80mm/`.
- Ajusta permisos y roles en la carpeta `roles-permissions`.

## Desarrollo

- El plugin usa hooks y filtros propios de WooCommerce PDF Invoices.
- Incluye helpers para generación de QR y AJAX para interactividad sin recarga.
