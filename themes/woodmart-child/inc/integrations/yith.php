<?php
/**
 * Integración con YITH WooCommerce Barcodes and QR Codes
 * Este archivo contiene hooks y funciones para extender o adaptar la funcionalidad
 * del plugin YITH Barcode en conjunto con WooCommerce y nuestro sistema personalizado.
 *
 * Ubicación: integrations/yith.php
 */

/**
 * Verifica que YITH Barcode esté activo y carga funciones de integración
 */
add_action('init', function () {
    if (!function_exists('yith_barcode_get_barcode')) {
        // YITH Barcode no está activo, salir
        return;
    }

    // Aquí puedes enganchar funciones personalizadas o filtros

    // Ejemplo: agregar metadatos o validar códigos antes de guardar
    add_action('woocommerce_process_product_meta', 'jl22_yith_save_barcode_meta', 10, 1);

    // Ejemplo: modificar el comportamiento del escaneo de códigos en el backend
    add_filter('yith_barcode_scan_product', 'jl22_yith_filter_scanned_product', 10, 2);
});

/**
 * Guarda meta personalizado o ajusta datos relacionados con el barcode
 *
 * @param int $post_id ID del producto
 */
function jl22_yith_save_barcode_meta($post_id) {
    if (isset($_POST['_jl22_custom_barcode'])) {
        update_post_meta($post_id, '_jl22_custom_barcode', sanitize_text_field($_POST['_jl22_custom_barcode']));
    }
}

/**
 * Filtra el producto detectado por el escaneo de barcode (ejemplo de personalización)
 *
 * @param WC_Product|null $product Producto detectado por YITH
 * @param string $barcode Código escaneado
 * @return WC_Product|null Producto modificado o nulo
 */
function jl22_yith_filter_scanned_product($product, $barcode) {
    // Aquí podrías agregar lógica extra para validar o modificar el producto
    // Por ejemplo, si el barcode tiene un prefijo especial lo interpretas diferente

    if (strpos($barcode, 'JL22-') === 0) {
        // Buscar producto por meta personalizada con código limpio
        $clean_code = substr($barcode, 5);

        $args = [
            'post_type'  => 'product',
            'meta_key'   => '_jl22_custom_barcode',
            'meta_value' => $clean_code,
            'posts_per_page' => 1,
            'fields' => 'ids',
        ];

        $query = new WP_Query($args);
        if ($query->have_posts()) {
            $product_id = $query->posts[0];
            return wc_get_product($product_id);
        }

        return null;
    }

    return $product;
}
