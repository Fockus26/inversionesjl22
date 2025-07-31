<?php
/**
 * ===========================
 * SKU AUTOMÁTICO POR CATEGORÍA
 * ===========================
**/

// Genera un SKU basado en la primera categoría asignada al producto
add_action('woocommerce_process_product_meta', 'generar_sku_por_categoria_limpio', 99, 2);
function generar_sku_por_categoria_limpio($post_id, $post) {
    $product = wc_get_product($post_id);
    if (!$product || $product->get_sku()) return;

    $terms = get_the_terms($post_id, 'product_cat');
    $prefix = 'SKU';

    if ($terms && !is_wp_error($terms)) {
        $nombre = reset($terms)->name;
        // Normaliza acentos y caracteres especiales
        $nombre = strtr($nombre, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'ñ' => 'n', 'Ñ' => 'N'
        ]);
        $nombre = preg_replace('/[^\p{L}\p{N} ]+/u', '', $nombre);
        $nombre = strtoupper(str_replace(' ', '-', $nombre));
        if (!empty($nombre)) $prefix = $nombre;
    }

    // Evita duplicados en el SKU
    $sku_base = $prefix . '-' . $post_id;
    $sku_final = $sku_base;
    $i = 1;

    while (wc_get_product_id_by_sku($sku_final)) {
        $sku_final = $sku_base . '-' . $i;
        $i++;
    }

    $product->set_sku($sku_final);
    $product->save();
}
