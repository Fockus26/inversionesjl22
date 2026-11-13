<?php
/**
 * The order invoice in PDF, made by the plugin itself (its own Dompdf, includes/pdf.php): the
 * thermal-roll invoice the warehouse prints, with the QR it scans (qr-scan.php).
 *
 * What it replaces from PDF Invoices & Packing Slips (vendored, 5.16.6): its template
 * templates/Factura80mm/ (now templates/pdf/invoice.php and assets/css/pdf-invoice.css) and the
 * shop's customizations in its custom/inversionesjl-pdf.php: paper from WooCommerce › Documentos,
 * the automatic sheet height, the typeface and weight, the 58 mm adjustments. The HTML handed to
 * Dompdf is the one that plugin built, and Dompdf is the same version with the same options, so
 * the PDF comes out the same (context/PHASE_LOG/feat-pdf-propio.md has the comparison).
 *
 * Where it is obtained, the same places as with PDF Invoices, each one only for who may:
 *   - the invoice button in the orders list (woocommerce_admin_order_actions_end);
 *   - the "Crear PDF" box of the order screen;
 *   - the "PDF Factura" bulk action of the orders list (several orders, one PDF).
 * All three go to admin-ajax.php?action=inversionesjl22_invoice&order_ids=…&_wpnonce=…, which
 * checks the nonce, the capability and, per order, the role's rules
 * (inversionesjl22_site_user_can_get_invoice()): a role that does not see an order does not get
 * its invoice even with the link.
 *
 * The switch: while PDF Invoices is active it keeps doing all of this and nothing here adds a
 * button, a box or a bulk action (inversionesjl22_site_own_invoice()); the endpoint answers
 * anyway, behind the same checks. Deactivating PDF Invoices (migration 007) moves everything
 * here; reactivating it moves it back.
 *
 * Not rebuilt, because the shop does not use it (see the PHASE_LOG): the packing slip, the
 * invoice numbers and dates of PDF Invoices, its "printed" marks, its document settings.
 */

defined( 'ABSPATH' ) || exit;

const INVERSIONESJL22_SITE_INVOICE_ACTION = 'inversionesjl22_invoice';
const INVERSIONESJL22_SITE_INVOICE_MAX    = 100; // orders in one bulk PDF

/**
 * Whether the plugin's own invoice is the one in use: when PDF Invoices is not active.
 * Filter 'inversionesjl22_site_own_invoice' to force it either way.
 */
function inversionesjl22_site_own_invoice(): bool {
	return (bool) apply_filters( 'inversionesjl22_site_own_invoice', ! class_exists( 'WPO_WCPDF' ) );
}

/**
 * Whether the current user may get the invoice of an order: someone who edits orders and, with a
 * role of WooCommerce › Roles y permisos, only for an order in a status the role sees and not
 * excluded in its "Factura PDF" column (exclude['qr_actions']). The rule the orders list's
 * invoice button already followed (roles-actions.php), now for every way of getting it.
 */
function inversionesjl22_site_user_can_get_invoice( $order ): bool {
	if ( ! $order instanceof WC_Order || 'shop_order' !== $order->get_type() ) {
		return false;
	}
	if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce_orders' ) ) {
		return false;
	}
	$status = $order->get_status();
	if ( 'trash' === $status ) {
		return false;
	}
	$ruleset = function_exists( 'getUserPermissions' ) ? getUserPermissions() : null;
	if ( null === $ruleset ) {
		return true; // administrator, or a role without rules: as the list always did
	}
	if ( empty( $ruleset[ $status ] ) ) {
		return false;
	}
	return ! in_array( $status, (array) ( $ruleset['exclude_status_actions']['qr_actions'] ?? array() ), true );
}

/**
 * The link to the PDF of one or several orders (nonce of the current user).
 *
 * @param int[] $order_ids
 */
function inversionesjl22_site_invoice_url( array $order_ids ): string {
	return add_query_arg(
		array(
			'action'    => INVERSIONESJL22_SITE_INVOICE_ACTION,
			'order_ids' => implode( ',', array_map( 'absint', $order_ids ) ),
			'_wpnonce'  => wp_create_nonce( INVERSIONESJL22_SITE_INVOICE_ACTION ),
		),
		admin_url( 'admin-ajax.php' )
	);
}

/*
 * ---------------------------------------------------------------------------------------------
 * The document
 * ---------------------------------------------------------------------------------------------
 */

/**
 * The rows of the products table, as PDF Invoices gave them to the template
 * (OrderDocumentMethods::get_order_items()): the unit price is the line subtotal with tax
 * (before discount) over the quantity, in the order's currency.
 *
 * @return array<int, array{name: string, quantity: int|float, single_price: string, variation_id: int, item: WC_Order_Item_Product}>
 */
function inversionesjl22_site_invoice_items( WC_Order $order ): array {
	$rows = array();
	foreach ( $order->get_items() as $item_id => $item ) {
		$qty            = $item->get_quantity();
		$divide_by      = 0 != $qty ? abs( $qty ) : 1; // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- as PDF Invoices
		$rows[ $item_id ] = array(
			'name'         => apply_filters( 'woocommerce_order_item_name', $item->get_name(), $item, false ),
			'quantity'     => $qty,
			'single_price' => wc_price( $order->get_line_subtotal( $item, true ) / $divide_by, array( 'currency' => $order->get_currency() ) ),
			'variation_id' => (int) $item->get_variation_id(),
			'item'         => $item,
		);
	}
	return $rows;
}

/**
 * The "Total" row the template prints: WooCommerce's order total without the colon, and for an
 * order with refunds the total before them (PDF Invoices' get_woocommerce_totals()). Null when
 * the label is not "Total" (the template then prints no total, as it did).
 *
 * @return array{label: string, value: string}|null
 */
function inversionesjl22_site_invoice_total( WC_Order $order ): ?array {
	$totals = $order->get_order_item_totals();
	if ( empty( $totals['order_total'] ) ) {
		return null;
	}
	$total = $totals['order_total'];
	$colon = strrpos( $total['label'], ':' );
	if ( ! empty( $colon ) ) {
		$total['label'] = substr_replace( $total['label'], '', $colon, 1 );
	}
	if ( 'Total' !== $total['label'] ) {
		return null;
	}

	if ( $order->get_total_refunded() ) {
		$total['value'] = wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) );
		if ( wc_tax_enabled() && 'incl' === get_option( 'woocommerce_tax_display_cart' ) ) {
			$taxes = array();
			if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) {
				foreach ( $order->get_tax_totals() as $tax ) {
					$taxes[] = sprintf( '%s %s', $tax->formatted_amount, $tax->label );
				}
			} else {
				$taxes[] = sprintf( '%s %s', wc_price( $order->get_total_tax(), array( 'currency' => $order->get_currency() ) ), WC()->countries->tax_or_vat() );
			}
			/* translators: %s: the taxes the total includes */
			$total['value'] .= ' ' . sprintf( __( '(incluye %s)', 'inversionesjl22-site' ), implode( ', ', $taxes ) );
		}
	}
	return array(
		'label' => (string) $total['label'],
		'value' => (string) $total['value'],
	);
}

/**
 * The CSS family of each typeface of WooCommerce › Documentos (keys of
 * inversionesjl22_site_document_fonts()): the families Dompdf has, its own or assets/fonts/.
 *
 * @return array<string, string>
 */
function inversionesjl22_site_document_font_families(): array {
	return array(
		'dejavu-sans-mono' => '"DejaVu Sans Mono", monospace',
		'dejavu-sans'      => '"DejaVu Sans", sans-serif',
		'dejavu-serif'     => '"DejaVu Serif", serif',
		'open-sans'        => '"Open Sans", sans-serif',
		'roboto-slab'      => '"Roboto Slab", serif',
		'segoe'            => '"Segoe", sans-serif',
		'helvetica'        => 'Helvetica, sans-serif',
		'times'            => 'Times, serif',
		'courier'          => 'Courier, monospace',
	);
}

/**
 * The styles after the template's: the currency symbol's font, the typeface and weight of
 * WooCommerce › Documentos, and the 58 mm adjustments. In that order, as PDF Invoices printed them
 * (its currency font, then custom/inversionesjl-pdf.php's two blocks).
 */
function inversionesjl22_site_invoice_extra_css(): string {
	$css = ".jl-currency-symbol { font-family: 'Currencies'; }\n";

	$text     = inversionesjl22_site_document_text();
	$families = inversionesjl22_site_document_font_families();
	if ( isset( $families[ $text['font'] ] ) ) {
		// The template sets its own font-family: forced on the whole document.
		$css .= 'body, body * { font-family: ' . $families[ $text['font'] ] . " !important; }\n";
	}
	if ( isset( inversionesjl22_site_document_weights()[ $text['font_weight'] ] ) ) {
		// Only on body: the text inherits it and the template's .bold stays bold.
		$css .= 'body { font-weight: ' . $text['font_weight'] . "; }\n";
	}

	// The template is written for 76 mm of print width; on the 58 mm roll it is readjusted here:
	// a 54 mm body, smaller text, customer data with the label over the value, more room for the
	// price.
	if ( 58 === inversionesjl22_site_document_paper()['mm'] ) {
		$css .= '@page { size: 58mm auto; margin: 2mm 2mm 5mm 2mm; }
body { width: 54mm !important; font-size: 7.5pt; }
.document-title { font-size: 9pt; }
.heading { font-size: 1.3em; }
.qr-code img { width: 18mm !important; height: 18mm !important; }
.client-info-table td { display: block; width: 100%; padding: 0; }
.client-info-table tr { display: block; margin-bottom: 1mm; }
.items-table th, .items-table td { font-size: 7pt; }
.product-name { width: 50%; font-size: 6.5pt; }
.product-qty { width: 14%; padding: 0; }
.product-price { width: 36%; }
.product-sku { font-size: 6.5pt; }
.variation-attributes, .attribute { font-size: 6.5pt; }
.total-value { margin-left: 1mm; }
.delivery-info-table { font-size: 7pt; }
.delivery-info-table td, .delivery-info-table td:first-child { display: block; width: 100%; }
.delivery-info-table tr { display: block; margin-bottom: 1mm; }
.delivery-cut-message, .delivery-footer { font-size: 7pt; }
';
	}
	return $css;
}

/**
 * The currency symbol in its own font (assets/fonts/currencies.ttf), as PDF Invoices did with
 * its "currency font" setting on: Dompdf's core fonts lack some symbols.
 */
function inversionesjl22_site_invoice_currency_symbol( $symbol ) {
	return '<span class="jl-currency-symbol">' . $symbol . '</span>';
}

/**
 * The HTML of the invoice of one or several orders: each order's template, joined by page
 * breaks, in one document. The orders are not checked here: the caller does it.
 *
 * @param int[] $order_ids
 */
function inversionesjl22_site_invoice_html( array $order_ids ): string {
	add_filter( 'woocommerce_currency_symbol', 'inversionesjl22_site_invoice_currency_symbol', 10001 );

	$bodies = array();
	try {
		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order instanceof WC_Order ) {
				continue;
			}
			$order_items  = inversionesjl22_site_invoice_items( $order );
			$order_total  = inversionesjl22_site_invoice_total( $order );
			$order_number = ltrim( (string) $order->get_order_number(), '#' );
			ob_start();
			include INVERSIONESJL22_SITE_DIR . 'templates/pdf/invoice.php';
			$bodies[] = ob_get_clean();
		}
	} finally {
		remove_filter( 'woocommerce_currency_symbol', 'inversionesjl22_site_invoice_currency_symbol', 10001 );
	}

	$css = (string) file_get_contents( INVERSIONESJL22_SITE_DIR . 'assets/css/pdf-invoice.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a file of the plugin

	return '<!DOCTYPE html>
<html ' . get_language_attributes() . '>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>' . esc_html__( 'Factura', 'inversionesjl22-site' ) . '</title>
<style type="text/css">' . $css . '</style>
<style type="text/css">' . inversionesjl22_site_invoice_extra_css() . '</style>
</head>
<body class="invoice invoice-template">
' . implode( "\n<div style=\"page-break-before: always;\"></div>\n", $bodies ) . '
</body>
</html>';
}

/**
 * The PDF of the invoice of one or several orders: the configured roll width, and a sheet as
 * tall as the longest copy (inversionesjl22_site_invoice_fit_height()).
 *
 * @param int[] $order_ids
 */
function inversionesjl22_site_invoice_pdf( array $order_ids ): string {
	$html    = inversionesjl22_site_invoice_html( $order_ids );
	$options = array(
		'defaultFont'             => 'dejavu sans',
		'isHtml5ParserEnabled'    => true,
		'isFontSubsettingEnabled' => true,
		// The QR is a data: URI; nothing else is loaded (PDF Invoices allowed remote files).
		'isRemoteEnabled'         => false,
		'chroot'                  => array( INVERSIONESJL22_SITE_DIR ),
	);
	$dompdf  = inversionesjl22_site_dompdf( $options );
	$dompdf->loadHtml( $html );
	$dompdf->setPaper( array( 0, 0, inversionesjl22_site_document_paper()['width_pt'], 792.0 ) );
	inversionesjl22_site_invoice_fit_height( $dompdf, $html, $options );
	$dompdf->render();
	return (string) $dompdf->output();
}

/**
 * The automatic sheet height of the thermal roll, from custom/inversionesjl-pdf.php.
 *
 * With a fixed height (792 pt ≈ 279 mm) an order with many products did not fit on one sheet and
 * the copy split in two; printing on the roll, the printer lost what fell on the cut. A first
 * render on a very tall sheet measures the content, and the sheet gets the height that makes
 * every copy (every page break) fit whole: all sheets as tall as the longest copy.
 *
 * @param \InversionesJL22Site\Vendor\Dompdf\Dompdf $dompdf
 */
function inversionesjl22_site_invoice_fit_height( $dompdf, string $html, array $options ): void {
	$width = (float) $dompdf->getPaperSize()[2];
	try {
		$probe_height = 14000; // ≈ 4.9 m, more than any order
		$bottom       = 0;
		$probe        = inversionesjl22_site_dompdf( $options );
		$probe->setPaper( array( 0, 0, $width, $probe_height ) );
		$probe->setCallbacks(
			array(
				array(
					'event' => 'end_frame',
					'f'     => static function ( $frame ) use ( &$bottom ) {
						$box    = $frame->get_border_box();
						$bottom = max( $bottom, $box['y'] + $box['h'] );
					},
				),
			)
		);
		$probe->loadHtml( $html );
		$probe->render();
		unset( $probe );

		if ( $bottom > 0 && $bottom < $probe_height ) {
			// @page's bottom margin (5 mm) and room for the printer's cut.
			$dompdf->setPaper( array( 0, 0, $width, max( 50 * 72 / 25.4, ceil( $bottom + 10 * 72 / 25.4 ) ) ) );
		}
	} catch ( \Throwable $e ) {
		// The fixed height, as always, if the measurement fails.
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->warning( 'Alto automático de la factura: ' . $e->getMessage(), array( 'source' => 'inversionesjl22-site' ) );
		}
	}
}

/*
 * ---------------------------------------------------------------------------------------------
 * The endpoint
 * ---------------------------------------------------------------------------------------------
 */

add_action( 'wp_ajax_' . INVERSIONESJL22_SITE_INVOICE_ACTION, 'inversionesjl22_site_invoice_endpoint' );

/**
 * admin-ajax.php?action=inversionesjl22_invoice&order_ids=1,2&_wpnonce=…: the PDF inline, or a
 * 403 (400 for a request with no valid order) and never the PDF. Logged-in users only (no
 * wp_ajax_nopriv_).
 */
function inversionesjl22_site_invoice_endpoint(): void {
	nocache_headers();

	if ( ! check_ajax_referer( INVERSIONESJL22_SITE_INVOICE_ACTION, '_wpnonce', false ) ) {
		inversionesjl22_site_invoice_deny( 403 );
	}

	$raw = isset( $_GET['order_ids'] ) && is_string( $_GET['order_ids'] ) ? sanitize_text_field( wp_unslash( $_GET['order_ids'] ) ) : '';
	$ids = array_values( array_unique( array_filter( array_map( 'absint', explode( ',', $raw ) ) ) ) );
	if ( ! $ids || count( $ids ) > INVERSIONESJL22_SITE_INVOICE_MAX ) {
		inversionesjl22_site_invoice_deny( 400 );
	}

	foreach ( $ids as $id ) {
		if ( ! inversionesjl22_site_user_can_get_invoice( wc_get_order( $id ) ) ) {
			inversionesjl22_site_invoice_deny( 403 );
		}
	}

	$pdf = inversionesjl22_site_invoice_pdf( $ids );

	// The name PDF Invoices gave it: invoice-<number>.pdf, or invoice-<date>.pdf for several.
	$name = 1 === count( $ids ) ? ltrim( (string) wc_get_order( $ids[0] )->get_order_number(), '#' ) : gmdate( 'Y-m-d' );
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: inline; filename="' . rawurlencode( sanitize_file_name( 'invoice-' . $name . '.pdf' ) ) . '"' );
	header( 'Content-Length: ' . strlen( $pdf ) );
	echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the PDF's bytes
	exit;
}

function inversionesjl22_site_invoice_deny( int $code ): void {
	wp_die(
		esc_html__( 'No tienes permiso para ver la factura de este pedido.', 'inversionesjl22-site' ),
		esc_html__( 'Factura', 'inversionesjl22-site' ),
		array( 'response' => $code )
	);
}

/*
 * ---------------------------------------------------------------------------------------------
 * Where it is obtained (only with the own invoice in use: PDF Invoices inactive)
 * ---------------------------------------------------------------------------------------------
 */

if ( is_admin() ) {
	add_action( 'admin_init', 'inversionesjl22_site_invoice_entries' );
}

function inversionesjl22_site_invoice_entries(): void {
	if ( ! inversionesjl22_site_own_invoice() ) {
		return;
	}
	add_action( 'woocommerce_admin_order_actions_end', 'inversionesjl22_site_invoice_list_button' );
	add_action( 'add_meta_boxes', 'inversionesjl22_site_invoice_meta_box', 10, 2 );
	foreach ( array( 'edit-shop_order', 'woocommerce_page_wc-orders' ) as $screen ) {
		// Priority 10: before roles-actions.php (20), which keeps it only in a status view.
		add_filter( 'bulk_actions-' . $screen, 'inversionesjl22_site_invoice_bulk_action' );
		add_filter( 'handle_bulk_actions-' . $screen, 'inversionesjl22_site_invoice_handle_bulk', 10, 3 );
	}
	add_action( 'admin_enqueue_scripts', 'inversionesjl22_site_invoice_assets' );
}

/**
 * The invoice button in the orders list, after WooCommerce's actions (where PDF Invoices put it).
 */
function inversionesjl22_site_invoice_list_button( $order ): void {
	if ( ! inversionesjl22_site_user_can_get_invoice( $order ) ) {
		return;
	}
	$label = __( 'PDF Factura', 'inversionesjl22-site' );
	printf(
		'<a href="%1$s" class="button tips jl22-invoice" target="_blank" rel="noopener" data-tip="%2$s" aria-label="%3$s"></a>',
		esc_url( inversionesjl22_site_invoice_url( array( $order->get_id() ) ) ),
		esc_attr( $label ),
		/* translators: %s: the order number */
		esc_attr( sprintf( __( 'Factura PDF del pedido %s (se abre en una pestaña nueva)', 'inversionesjl22-site' ), $order->get_order_number() ) )
	);
}

/**
 * "Crear PDF" in the order screen's sidebar, for who may get this order's invoice.
 */
function inversionesjl22_site_invoice_meta_box( $screen_id, $post_or_order = null ): void {
	if ( ! in_array( $screen_id, array( 'shop_order', 'woocommerce_page_wc-orders' ), true ) ) {
		return;
	}
	$order = $post_or_order instanceof WP_Post ? wc_get_order( $post_or_order->ID ) : $post_or_order;
	if ( ! inversionesjl22_site_user_can_get_invoice( $order ) ) {
		return;
	}
	add_meta_box(
		'jl22-invoice-box',
		__( 'Crear PDF', 'inversionesjl22-site' ),
		static function () use ( $order ) {
			printf(
				'<ul class="jl22-invoice-actions"><li><a href="%1$s" class="button" target="_blank" rel="noopener">%2$s<span class="screen-reader-text"> %3$s</span></a></li></ul>',
				esc_url( inversionesjl22_site_invoice_url( array( $order->get_id() ) ) ),
				esc_html__( 'Factura', 'inversionesjl22-site' ),
				esc_html__( '(se abre en una pestaña nueva)', 'inversionesjl22-site' )
			);
		},
		$screen_id,
		'side',
		'default'
	);
}

function inversionesjl22_site_invoice_bulk_action( $actions ) {
	$actions[ INVERSIONESJL22_SITE_INVOICE_ACTION ] = __( 'PDF Factura', 'inversionesjl22-site' );
	return $actions;
}

/**
 * Without JavaScript the bulk action reloads the list: it goes to the PDF. With it
 * (assets/js/invoice-bulk.js) the PDF opens in a new tab and the list stays, as with PDF Invoices.
 */
function inversionesjl22_site_invoice_handle_bulk( $redirect, $action, $ids ) {
	if ( INVERSIONESJL22_SITE_INVOICE_ACTION !== $action || ! $ids ) {
		return $redirect;
	}
	return inversionesjl22_site_invoice_url( array_slice( array_map( 'absint', (array) $ids ), 0, INVERSIONESJL22_SITE_INVOICE_MAX ) );
}

function inversionesjl22_site_invoice_assets(): void {
	$screen = inversionesjl22_site_admin_screen();
	if ( 'orders' !== $screen && 'order' !== $screen ) {
		return;
	}
	wp_enqueue_style( 'inversionesjl22-invoice', INVERSIONESJL22_SITE_URL . 'assets/css/admin-invoice.css', array(), inversionesjl22_site_asset_version( 'assets/css/admin-invoice.css' ) );
	wp_add_inline_style( 'inversionesjl22-invoice', '.column-wc_actions a.button.jl22-invoice{background-image:url(' . esc_url( INVERSIONESJL22_SITE_URL . 'assets/images/invoice.svg' ) . ')}' );
	if ( 'orders' === $screen ) {
		wp_enqueue_script( 'inversionesjl22-invoice-bulk', INVERSIONESJL22_SITE_URL . 'assets/js/invoice-bulk.js', array(), inversionesjl22_site_asset_version( 'assets/js/invoice-bulk.js' ), true );
		wp_localize_script(
			'inversionesjl22-invoice-bulk',
			'jl22Invoice',
			array(
				'action' => INVERSIONESJL22_SITE_INVOICE_ACTION,
				'url'    => inversionesjl22_site_invoice_url( array() ),
				'max'    => INVERSIONESJL22_SITE_INVOICE_MAX,
			)
		);
	}
}
