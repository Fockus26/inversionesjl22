<?php
add_action('wpo_wcpdf_custom_report_pdf', 'pdfShippingReport');
function pdfShippingReport($args) {
     // Init
    $pdf = new \Dompdf\Dompdf();
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