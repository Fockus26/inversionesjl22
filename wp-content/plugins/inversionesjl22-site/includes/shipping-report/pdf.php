<?php
/**
 * Shipping report (WooCommerce › Informe de Envíos): a form picks country, courier, dates and
 * shift; admin-post.php?action=shipping_report finds the completed orders and streams an 80 mm
 * PDF. Split in one file per WPCodeBox snippet, each moved as is.
 *
 * This file: renders the HTML with Dompdf (bundled with the PDF invoices plugin) on 80 mm paper
 * and streams it. From Shipping Report › PDF (snippet 57), which only ran in wp-admin: same here.
 * Only change: Dompdf is WPO\IPS\Vendor\Dompdf\Dompdf, the name the PDF plugin gives it since it
 * prefixes its vendors; \Dompdf\Dompdf does not exist with PDF Invoices 5.16.6, so the report
 * was a fatal error.
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_admin() ) {
	return;
}

add_action('wpo_wcpdf_custom_report_pdf', 'pdfShippingReport');
function pdfShippingReport($args) {
     // Init
    $dompdfClass = class_exists('\WPO\IPS\Vendor\Dompdf\Dompdf') ? '\WPO\IPS\Vendor\Dompdf\Dompdf' : '\Dompdf\Dompdf';
    $pdf = new $dompdfClass();
    $paper80mm = [0, 0, 226.77, 841.89];
    $pdf->setPaper($paper80mm);
    
    // Render
    $html = pdfUIShippingReport($args);
    $pdf->loadHtml($html);
    $pdf->render();

    // Send
    $country = $args['country'];
    $company = $args['company'];
    $pdfTitle = "Informe de Envios | " . $country . " | " . $company . " | " . date('YmdHis') . ".pdf";
    $pdf->stream($pdfTitle, ["Attachment" => false]);
    exit;
}
