<?php
/**
 * Automatic SKU: when a product is saved in wp-admin without a SKU, it gets
 * <FIRST-CATEGORY>-<post_id> (accents and ñ normalized, anything but letters, digits and spaces
 * removed, spaces as hyphens, upper case), or SKU-<post_id> without a category; -1, -2… is
 * appended while that SKU is taken. A product that already has a SKU is left alone.
 *
 * Moved from the child theme (woodmart-child/inc/integrations/sku-generator.php). The child
 * theme is not active in production, so this did not run there until now.
 *
 * Changes since: the function has the plugin's prefix, because the child theme declares
 * generar_sku_por_categoria_limpio() after the plugins load (activating it was a fatal error);
 * if it is ever active, its copy finds the SKU already set and does nothing. And accents go
 * through remove_accents() instead of a list of á é í ó ú ñ, so ü, à or ç no longer reach the SKU.
 */

defined( 'ABSPATH' ) || exit;

add_action('woocommerce_process_product_meta', 'inversionesjl22_site_generate_sku', 99, 2);
function inversionesjl22_site_generate_sku($post_id, $post) {
    $product = wc_get_product($post_id);
    // Si ya tiene SKU o no es producto, salimos
    if (!$product || $product->get_sku()) return;

    $terms = get_the_terms($post_id, 'product_cat');
    $prefix = 'SKU';

    if ($terms && !is_wp_error($terms)) {
        $nombre = reset($terms)->name;
        // Normalizar acentos y caracteres especiales (á, ñ, ü, ç…)
        $nombre = remove_accents($nombre);
        // Quitar cualquier carácter que no sea letra, número o espacio
        $nombre = preg_replace('/[^\p{L}\p{N} ]+/u', '', $nombre);
        // Convertir a mayúsculas y reemplazar espacios por guiones
        $nombre = strtoupper(str_replace(' ', '-', $nombre));
        if (!empty($nombre)) $prefix = $nombre;
    }

    // Construir SKU base con post_id para evitar colisiones
    $sku_base = $prefix . '-' . $post_id;
    $sku_final = $sku_base;
    $i = 1;

    // Añadir sufijo incremental si SKU ya existe
    while (wc_get_product_id_by_sku($sku_final)) {
        $sku_final = $sku_base . '-' . $i;
        $i++;
    }

    $product->set_sku($sku_final);
    $product->save();
}
