<?php
/**
 * ===========================
 * SKU AUTOMÁTICO POR CATEGORÍA
 * ===========================
 * Al guardar un producto sin SKU, genera uno automático basado
 * en el nombre de la primera categoría asignada, normalizando texto
 * y asegurando que el SKU sea único.
**/

add_action('woocommerce_process_product_meta', 'generar_sku_por_categoria_limpio', 99, 2);
function generar_sku_por_categoria_limpio($post_id, $post) {
    $product = wc_get_product($post_id);
    // Si ya tiene SKU o no es producto, salimos
    if (!$product || $product->get_sku()) return;

    $terms = get_the_terms($post_id, 'product_cat');
    $prefix = 'SKU';

    if ($terms && !is_wp_error($terms)) {
        $nombre = reset($terms)->name;
        // Normalizar acentos y caracteres especiales
        $nombre = strtr($nombre, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'ñ' => 'n', 'Ñ' => 'N'
        ]);
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
