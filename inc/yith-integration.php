<?php
/**
 * ====================
 * INTEGRACIÓN CON YITH
 * ====================
**/

// Muestra código de barras desde el plugin YITH Barcodes
if (!function_exists('yith_wc_barcodes_and_qr_filter_call_back')) {
    function yith_wc_barcodes_and_qr_filter_call_back($order_id) {
        ob_start();
        $css = ob_get_clean();
        YITH_YWBC()->show_barcode($order_id, true, $css);
    }
    add_action('yith_wc_barcodes_and_qr_filter','yith_wc_barcodes_and_qr_filter_call_back', 10, 1);
}
