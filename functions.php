<?php
/**
 * Carga archivos funcionales divididos por responsabilidades
**/

$includes = [
    'enqueue.php',                 // Carga de estilos del child theme
    'sku-generator.php',           // Generador de SKU basado en categoría
    'custom-order-status.php',     // Nuevos estados de pedido personalizados
    'yith-integration.php',        // Integración con plugin YITH Barcodes
    'order-roles-visibility.php',  // Restricciones de visualización y acciones de pedidos según roles
    'order-status-transitions.php', // Acciones AJAX personalizadas para cambio de estado
];

foreach ($includes as $file) {
    require_once get_stylesheet_directory() . '/inc/' . $file;
}
