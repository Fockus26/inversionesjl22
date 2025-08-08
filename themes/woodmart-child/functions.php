<?php
/**
 * Carga archivos funcionales divididos por responsabilidades
**/

// Lista de archivos que contienen funciones específicas para el tema hijo (woodmart-child).
// Cada archivo está organizado por responsabilidad para mantener el código modular y fácil de mantener.
$includes = [
    'enqueue.php',                  // Registra y carga los estilos y scripts del child theme.
    'sku-generator.php',            // Genera automáticamente un SKU basado en la categoría del producto.
    'custom-order-status.php',      // Define y registra nuevos estados personalizados de pedidos en WooCommerce.
    'yith-integration.php',         // Añade integración con el plugin YITH Barcodes para códigos de barra.
    'order-roles-visibility.php',   // Controla la visibilidad de pedidos y las acciones disponibles según el rol del usuario.
    'order-status-transitions.php', // Define acciones AJAX personalizadas para cambiar estados de pedidos.
    'shipping-report.php',          // Agrega una vista en WooCommerce para mostrar un reporte de envíos.
    'pdf-shipping-report.php',      // Genera un PDF con el reporte de envíos.
];

// Bucle que recorre la lista de archivos e incluye cada uno desde la carpeta /inc del tema hijo.
// Esto asegura que todos los módulos estén disponibles para el sitio.
foreach ($includes as $file) {
    require_once get_stylesheet_directory() . '/inc/' . $file;
}
