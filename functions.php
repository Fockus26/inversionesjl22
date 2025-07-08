<?php
/**
 * ====================
 * UTILIDADES GENERALES
 * ====================
 */

// Verifica si el usuario actual tiene un rol específico
function user_has_role($role) {
    $user = wp_get_current_user();
    return in_array($role, (array) $user->roles);
}

/**
 * =====================
 * ESTILOS DEL CHILD THEME
 * =====================
 */

// Carga los estilos del tema hijo, asegurando dependencia del tema padre Woodmart
add_action('wp_enqueue_scripts', 'woodmart_child_enqueue_styles', 10010);
function woodmart_child_enqueue_styles() {
    wp_enqueue_style(
        'child-style',
        get_stylesheet_directory_uri() . '/style.css',
        ['woodmart-style'],
        woodmart_get_theme_info('Version')
    );
}

/**
 * ===========================
 * SKU AUTOMÁTICO POR CATEGORÍA
 * ===========================
 */

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

/**
 * ============================
 * ACCIONES DE PEDIDO SEGÚN ROL
 * ============================
 */

// Oculta las acciones rápidas en el preview del pedido para todos excepto administradores
add_filter('woocommerce_admin_order_preview_actions', function($actions, $order) {
    return current_user_can('administrator') ? $actions : [];
}, 10, 2);

// Limita los estados de pedido visibles para el rol seo_manager
add_filter('wc_order_statuses', function($statuses) {
    if (is_admin() && user_has_role('seo_manager')) {
        return array_intersect_key($statuses, array_flip(['wc-processing', 'wc-on-hold']));
    }
    return $statuses;
});

// Elimina estados del dropdown mediante JavaScript para el rol seo_manager
add_filter('woocommerce_admin_order_data_after_order_details', function($order) {
    if (!user_has_role('seo_manager')) return;
    ?>
    <script>
        jQuery(document).ready(function($) {
            const allowed = ['on-hold', 'processing'];
            $('#order_status option').each(function() {
                if (!allowed.includes($(this).val().replace('wc-', ''))) {
                    $(this).remove();
                }
            });
        });
    </script>
    <?php
});

// Filtra las acciones disponibles en cada pedido según el rol y el estado
add_filter('woocommerce_admin_order_actions', function($actions, $order) {
    // seo_manager no puede completar pedidos
    if (user_has_role('seo_manager')) {
        unset($actions['complete']);

    // storage_manager solo puede completar si el estado es 'processing'
    } elseif (user_has_role('storage_manager')) {
        if ($order->get_status() !== 'processing') {
            unset($actions['complete']);
        }
        unset($actions['processing'], $actions['invoice']);
    }

    return $actions;
}, 100, 2);

/**
 * =============================================
 * OCULTA FACTURA PDF PARA STORAGE_MANAGER EN "EN ESPERA"
 * =============================================
 */

// Elimina la acción de Factura PDF solo si el pedido está en espera y el usuario es storage_manager
add_filter('wpo_wcpdf_listing_actions', function($listing_actions, $order) {
    if ( current_user_can('administrator') ) return $listing_actions;

    if ( user_has_role('storage_manager') && $order->get_status() === 'on-hold' ) {
        unset( $listing_actions['invoice'] );
    }

    return $listing_actions;
}, 10, 2);

// Elimina opciones de acciones por lotes en la lista de pedidos según rol
add_filter('bulk_actions-edit-shop_order', function($acciones) {
    if (!current_user_can('administrator')) {
        unset($acciones['mark_cancelled'], $acciones['trash'], $acciones['delete']);
        if (user_has_role('seo_manager')) {
            unset($acciones['mark_completed']);
        } else {
            unset($acciones['mark_processing']);
        }
    }
    return $acciones;
}, 20);

// Impide el acceso a la edición de pedidos para roles no autorizados
add_action('admin_init', function () {
    if (is_admin() && isset($_GET['post'], $_GET['action']) && $_GET['action'] === 'edit') {
        $post_id = (int) $_GET['post'];
        if (get_post_type($post_id) === 'shop_order' && !current_user_can('administrator') && !user_has_role('seo_manager')) {
            wp_redirect(admin_url('edit.php?post_type=shop_order'));
            exit;
        }
    }
});

// Oculta la pestaña "Papelera" del listado de pedidos a no administradores
add_filter('views_edit-shop_order', function($views) {
    if (!current_user_can('administrator')) {
        unset($views['trash']);
    }
    return $views;
});

/**
 * ====================
 * INTEGRACIÓN CON YITH
 * ====================
 */

// Muestra código de barras desde el plugin YITH Barcodes
if (!function_exists('yith_wc_barcodes_and_qr_filter_call_back')) {
    function yith_wc_barcodes_and_qr_filter_call_back($order_id) {
        ob_start();
        $css = ob_get_clean();
        YITH_YWBC()->show_barcode($order_id, true, $css);
    }
    add_action('yith_wc_barcodes_and_qr_filter','yith_wc_barcodes_and_qr_filter_call_back', 10, 1);
}   
