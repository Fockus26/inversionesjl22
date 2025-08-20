<?php

// Verificamos que Dompdf esté cargado, si no, lo incluimos desde el plugin WooCommerce PDF Invoices & Packing Slips
if ( ! class_exists( '\Dompdf\Dompdf' ) ) {
    require_once WP_PLUGIN_DIR . '/woocommerce-pdf-invoices-packing-slips/lib/dompdf/autoload.inc.php';
}

/**
 * Genera un reporte PDF personalizado de envíos
 * Hook: 'wpo_wcpdf_custom_report_pdf'
 *
 * @param array $args Datos para el reporte:
 *                    - empresa_nombre: nombre de la empresa
 *                    - fecha_inicio: fecha inicial del rango
 *                    - fecha_fin: fecha final del rango
 *                    - pedidos: array de pedidos a incluir en el reporte
 */
add_action('wpo_wcpdf_custom_report_pdf', function($args) {
    $empresa_nombre = $args['empresa_nombre'];
    $fecha_inicio   = $args['fecha_inicio'];
    $fecha_fin      = $args['fecha_fin'];
    $pedidos        = $args['pedidos'];
    $pais           = $args['pais'];

    // Capturamos el HTML del reporte en buffer
    ob_start();

    // Incluir estilos CSS del template estándar de 80mm
    echo '<style>' . file_get_contents( WP_PLUGIN_DIR . '/woocommerce-pdf-invoices-packing-slips/templates/Factura80mm/style.css' ) . '</style>';

    // Incluir plantilla PHP que genera el contenido HTML del reporte de envíos
    include WP_CONTENT_DIR . '/plugins/woocommerce-pdf-invoices-packing-slips/templates/Factura80mm/shipping-report.php';

    $html = ob_get_clean();

    // Inicializar Dompdf y configurar tamaño de papel personalizado (ancho 80mm, alto A4)
    $pdf = new \Dompdf\Dompdf();
    $pdf->setPaper([0, 0, 226.77, 841.89]); // medidas en puntos (72pt = 1 inch)

    // Cargar el HTML generado y renderizar el PDF
    $pdf->loadHtml($html);
    $pdf->render();

    // Enviar PDF inline al navegador sin forzar descarga
    $pdf->stream("informe-envios-" . date('YmdHis') . ".pdf", ["Attachment" => false]);
});
