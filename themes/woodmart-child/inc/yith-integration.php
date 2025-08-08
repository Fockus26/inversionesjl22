<?php
/**
 * ====================
 * INTEGRACIÓN CON YITH
 * ====================
 * Muestra el código de barras en la orden usando
 * el plugin YITH Barcodes si está activo.
**/

if (!function_exists('yith_wc_barcodes_and_qr_filter_call_back')) {
    function yith_wc_barcodes_and_qr_filter_call_back($order_id) {
        // Captura output buffer para posible CSS (actualmente vacío)
        ob_start();
        $css = ob_get_clean();
        // Muestra el código de barras con la función del plugin YITH
        YITH_YWBC()->show_barcode($order_id, true, $css);
    }
    add_action('yith_wc_barcodes_and_qr_filter', 'yith_wc_barcodes_and_qr_filter_call_back', 10, 1);
}
