<?php
/**
 * Deactivates PDF Invoices & Packing Slips: the plugin makes the order invoice itself
 * (includes/invoice-pdf.php) and the shipping report already used its own Dompdf
 * (includes/pdf.php). With PDF Invoices inactive, the invoice button of the orders list, the
 * "Crear PDF" box and the "PDF Factura" bulk action are the plugin's, and so are the invoice
 * attached to the order emails and the "Factura" button of Mi cuenta (includes/invoice-customer.php).
 *
 * Deactivated, not deleted: its files and its wpo_wcpdf_* options and order meta stay. Undo:
 * activate it again (`wp plugin activate woocommerce-pdf-invoices-packing-slips`): the plugin
 * then steps aside on its own (inversionesjl22_site_own_invoice()). This migration does not run
 * twice, so it does not deactivate it again.
 *
 * Skipped (it throws, the version does not advance, it is retried on the next visit) if the
 * plugin's own invoice (the document, the email attachment and Mi cuenta) is not loaded: the
 * modules do not load while WPCodeBox is active, and without them deactivating PDF Invoices
 * would leave the shop with no invoice.
 */

defined( 'ABSPATH' ) || exit;

return static function ( callable $remember ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$pdf_invoices = 'woocommerce-pdf-invoices-packing-slips/woocommerce-pdf-invoices-packingslips.php';
	if ( ! is_plugin_active( $pdf_invoices ) ) {
		return;
	}
	if ( ! function_exists( 'inversionesjl22_site_invoice_pdf' ) || ! function_exists( 'inversionesjl22_site_invoice_attach' ) || ! function_exists( 'inversionesjl22_site_invoice_account_action' ) ) {
		throw new RuntimeException( 'la factura propia no está cargada: PDF Invoices sigue activo' );
	}
	$remember( 'plugin:woocommerce-pdf-invoices-packing-slips', 'active' );
	deactivate_plugins( $pdf_invoices, true );
};
