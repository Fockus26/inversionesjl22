<?php
/**
 * Shipping report (Inversiones JL22 › Informe de Envíos): a form picks country, courier, dates and
 * shift; admin-post.php?action=shipping_report finds the completed orders and streams a PDF
 * on the configured thermal paper (80 mm by default). Split in one file per WPCodeBox snippet, each moved as is.
 *
 * This file: renders the HTML with the plugin's own Dompdf (includes/pdf.php) on the configured
 * paper and streams it. From Shipping Report › PDF (snippet 57), which only ran in wp-admin: same
 * here. Changes: Dompdf is the plugin's (it was the copy inside PDF Invoices, then
 * WPO\IPS\Vendor\Dompdf\Dompdf: the same 3.1.6 with Dompdf's default options, so the report comes
 * out the same), and the paper width is Inversiones JL22 › Documentos'.
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_admin() ) {
	return;
}

/**
 * The report rendered by Dompdf, on the configured paper (Inversiones JL22 › Documentos). Shared by the
 * report (streamed below) and by the preview of that screen (->output()).
 *
 * @return \InversionesJL22Site\Vendor\Dompdf\Dompdf
 */
function inversionesjl22_site_shipping_report_render($args) {
    // Init
    $pdf = inversionesjl22_site_dompdf();
    // The width is the configured paper (Inversiones JL22 › Documentos): 80 mm unless changed.
    $paper = [0, 0, inversionesjl22_site_document_paper()['width_pt'], 841.89];
    $pdf->setPaper($paper);

    // Render
    $html = pdfUIShippingReport($args);
    $pdf->loadHtml($html);
    $pdf->render();
    return $pdf;
}

add_action('wpo_wcpdf_custom_report_pdf', 'pdfShippingReport');
function pdfShippingReport($args) {
    $pdf = inversionesjl22_site_shipping_report_render($args);

    // Send
    $country = $args['country'];
    $company = $args['company'];
    $pdfTitle = "Informe de Envios | " . $country . " | " . $company . " | " . date('YmdHis') . ".pdf";
    // Whatever another plugin printed into an open buffer (a stray space) would go before %PDF-.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $pdf->stream($pdfTitle, ["Attachment" => false]);
    exit;
}
