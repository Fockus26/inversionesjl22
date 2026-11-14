<?php
/**
 * The order invoice where it reaches the customer and the shop by email: attached to the order
 * emails, and the "Factura" button of Mi cuenta. The two things PDF Invoices & Packing Slips did
 * besides wp-admin (context/PHASE_LOG/feat-factura-correo-cuenta.md has the "before"); the
 * document itself is includes/invoice-pdf.php's.
 *
 * Same switch as invoice-pdf.php: while PDF Invoices is active it attaches its own PDF and shows
 * its own button, and nothing here runs (inversionesjl22_site_own_invoice()). The customers'
 * endpoint answers anyway, behind its checks.
 *
 * - Emails: the five PDF Invoices was set to attach it to (wpo_wcpdf_documents_settings_invoice
 *   › attach_to_email_ids): Nuevo pedido, Pedido completado, Pedido reembolsado, Factura / detalles
 *   del pedido, Nota para el cliente. File factura-<order number>.pdf: PDF Invoices named it
 *   factura-<its invoice number>.pdf, a numbering not rebuilt (the invoice shows the order
 *   number). Generated into a
 *   temporary folder and deleted once the email is sent; if it fails the email goes without it
 *   (logged in WooCommerce › Estado › Registros, source inversionesjl22-site).
 * - Mi cuenta: PDF Invoices' setting was "available": the button only for an order whose invoice
 *   exists. Its invoice existed once created, in practice by the "Nuevo pedido" attachment; here
 *   an order has an invoice if PDF Invoices made one (_wcpdf_invoice_date) or this plugin did
 *   (_inversionesjl22_invoice_date, set when attaching it or getting it from wp-admin). Any status.
 *   It shows wherever WooCommerce lists an order's actions: Mi cuenta › Pedidos, the order's page
 *   and the order-received page.
 * - Who downloads it: the logged-in owner of the order, through
 *   admin-ajax.php?action=inversionesjl22_my_invoice&order_id=…&_wpnonce=… (a nonce per order and
 *   user). Not a guest (PDF Invoices' links were "logged_in" only: a guest got no link), not
 *   another customer: 403. A warehouse or SEO user who is also a customer follows these rules for
 *   their own orders here; wp-admin keeps its own rules (invoice-pdf.php).
 */

defined( 'ABSPATH' ) || exit;

const INVERSIONESJL22_SITE_MY_INVOICE_ACTION = 'inversionesjl22_my_invoice';
const INVERSIONESJL22_SITE_INVOICE_META      = '_inversionesjl22_invoice_date';

/**
 * The emails that carry the invoice. Filter 'inversionesjl22_site_invoice_email_ids'.
 *
 * @return string[]
 */
function inversionesjl22_site_invoice_email_ids(): array {
	return (array) apply_filters(
		'inversionesjl22_site_invoice_email_ids',
		array( 'new_order', 'customer_completed_order', 'customer_refunded_order', 'customer_invoice', 'customer_note' )
	);
}

/**
 * Whether the order has an invoice: made by PDF Invoices, or by this plugin.
 */
function inversionesjl22_site_invoice_exists( WC_Order $order ): bool {
	return '' !== (string) $order->get_meta( '_wcpdf_invoice_date' ) || '' !== (string) $order->get_meta( INVERSIONESJL22_SITE_INVOICE_META );
}

/**
 * Notes that the order has an invoice (the first time it is made), as PDF Invoices did when it
 * created one: from then on Mi cuenta offers it.
 */
function inversionesjl22_site_invoice_mark_created( $order ): void {
	if ( ! $order instanceof WC_Order || inversionesjl22_site_invoice_exists( $order ) ) {
		return;
	}
	$order->update_meta_data( INVERSIONESJL22_SITE_INVOICE_META, (string) time() );
	$order->save_meta_data();
}

/*
 * ---------------------------------------------------------------------------------------------
 * Emails
 * ---------------------------------------------------------------------------------------------
 */

add_filter( 'woocommerce_email_attachments', 'inversionesjl22_site_invoice_attach', 99, 4 );
add_action( 'woocommerce_email_sent', 'inversionesjl22_site_invoice_attachments_cleanup' );
add_action( 'shutdown', 'inversionesjl22_site_invoice_attachments_cleanup' );

/**
 * The temporary files of this request's attachments (one folder each): remembers $add, or with
 * $take returns them and forgets them.
 *
 * @return string[]
 */
function inversionesjl22_site_invoice_attachment_files( string $add = '', bool $take = false ): array {
	static $files = array();
	if ( '' !== $add ) {
		$files[] = $add;
	}
	$out = $files;
	if ( $take ) {
		$files = array();
	}
	return $out;
}

/**
 * woocommerce_email_attachments: the invoice PDF in the configured emails of an order.
 *
 * @param array         $attachments
 * @param string        $email_id
 * @param mixed         $order
 * @param WC_Email|null $email
 */
function inversionesjl22_site_invoice_attach( $attachments, $email_id = '', $order = null, $email = null ) {
	$attachments = is_array( $attachments ) ? $attachments : array();
	if ( ! inversionesjl22_site_own_invoice() || ! $order instanceof WC_Order || 'shop_order' !== $order->get_type() ) {
		return $attachments;
	}
	if ( ! in_array( (string) $email_id, inversionesjl22_site_invoice_email_ids(), true ) ) {
		return $attachments;
	}

	try {
		// The order again from the database, as PDF Invoices did: emails sent one after another in
		// the same request must not see stale data.
		$order = wc_get_order( $order->get_id() );
		if ( ! $order instanceof WC_Order ) {
			return $attachments;
		}
		$pdf = inversionesjl22_site_invoice_pdf( array( $order->get_id() ) );
		if ( 0 !== strpos( $pdf, '%PDF-' ) ) {
			throw new RuntimeException( 'el PDF salió vacío o inválido' );
		}

		// A folder of its own, so the file keeps the name the customer sees.
		$dir = trailingslashit( get_temp_dir() ) . 'jl22-invoice-' . wp_generate_password( 12, false, false );
		if ( ! wp_mkdir_p( $dir ) ) {
			throw new RuntimeException( 'no se pudo crear la carpeta temporal ' . $dir );
		}
		$file = $dir . '/' . sanitize_file_name( 'factura-' . ltrim( (string) $order->get_order_number(), '#' ) . '.pdf' );
		if ( false === file_put_contents( $file, $pdf ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- a temporary file
			throw new RuntimeException( 'no se pudo escribir ' . $file );
		}
		inversionesjl22_site_invoice_attachment_files( $file );
		$attachments[] = $file;
		inversionesjl22_site_invoice_mark_created( $order );
	} catch ( \Throwable $e ) {
		// The email goes without the invoice rather than not at all.
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error(
				sprintf( 'Factura del pedido %d no adjunta al correo %s: %s', $order instanceof WC_Order ? $order->get_id() : 0, $email_id, $e->getMessage() ),
				array( 'source' => 'inversionesjl22-site' )
			);
		}
	}
	return $attachments;
}

/**
 * Deletes the temporary attachments once WooCommerce has sent the email (and, in any case, at
 * the end of the request).
 */
function inversionesjl22_site_invoice_attachments_cleanup(): void {
	foreach ( inversionesjl22_site_invoice_attachment_files( '', true ) as $file ) {
		if ( is_file( $file ) ) {
			wp_delete_file( $file );
		}
		$dir = dirname( $file );
		if ( is_dir( $dir ) && 0 === strpos( basename( $dir ), 'jl22-invoice-' ) ) {
			@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- empty folder of our own
		}
	}
}

/*
 * ---------------------------------------------------------------------------------------------
 * Mi cuenta
 * ---------------------------------------------------------------------------------------------
 */

add_filter( 'woocommerce_my_account_my_orders_actions', 'inversionesjl22_site_invoice_account_action', 999, 2 );

/**
 * Whether the current user may download this order's invoice as its customer: logged in, the
 * order's owner, an order (not a refund) out of the trash.
 */
function inversionesjl22_site_customer_can_get_invoice( $order ): bool {
	if ( ! $order instanceof WC_Order || 'shop_order' !== $order->get_type() || 'trash' === $order->get_status() ) {
		return false;
	}
	$user_id = get_current_user_id();
	return $user_id > 0 && (int) $order->get_customer_id() === $user_id;
}

/**
 * The customer's link to an order's invoice (nonce of this order and user).
 */
function inversionesjl22_site_my_invoice_url( WC_Order $order ): string {
	return add_query_arg(
		array(
			'action'   => INVERSIONESJL22_SITE_MY_INVOICE_ACTION,
			'order_id' => $order->get_id(),
			'_wpnonce' => wp_create_nonce( INVERSIONESJL22_SITE_MY_INVOICE_ACTION . '-' . $order->get_id() ),
		),
		admin_url( 'admin-ajax.php' )
	);
}

/**
 * woocommerce_my_account_my_orders_actions: "Factura" for the owner, once the order has one.
 *
 * @param array $actions
 * @param mixed $order
 */
function inversionesjl22_site_invoice_account_action( $actions, $order ) {
	if ( ! inversionesjl22_site_own_invoice() || ! is_array( $actions ) ) {
		return $actions;
	}
	if ( ! inversionesjl22_site_customer_can_get_invoice( $order ) || ! inversionesjl22_site_invoice_exists( $order ) ) {
		return $actions;
	}
	$actions['invoice'] = array(
		'url'        => inversionesjl22_site_my_invoice_url( $order ),
		'name'       => __( 'Factura', 'inversionesjl22-site' ),
		/* translators: %s: the order number */
		'aria-label' => sprintf( __( 'Factura del pedido %s (PDF, se abre en una pestaña nueva)', 'inversionesjl22-site' ), $order->get_order_number() ),
	);
	return $actions;
}

add_action( 'wp_enqueue_scripts', 'inversionesjl22_site_invoice_account_assets' );

/**
 * Opens the invoice in a new tab, as PDF Invoices did ("download_display: display"): WooCommerce
 * prints the actions without a target, so a line of script adds it.
 */
function inversionesjl22_site_invoice_account_assets(): void {
	if ( ! inversionesjl22_site_own_invoice() || ! is_user_logged_in() ) {
		return;
	}
	$account  = function_exists( 'is_account_page' ) && is_account_page();
	$received = function_exists( 'is_order_received_page' ) && is_order_received_page();
	if ( ! $account && ! $received ) {
		return;
	}
	// Visible focus: the theme removes it from Mi cuenta's buttons (outline and shadow: none). The
	// plugin's focus ring (--jl-focus of the admin screens, 9:1 on white).
	wp_register_style( 'inversionesjl22-my-invoice', false, array(), INVERSIONESJL22_SITE_VERSION );
	wp_enqueue_style( 'inversionesjl22-my-invoice' );
	wp_add_inline_style(
		'inversionesjl22-my-invoice',
		'a.button.invoice[href*="action=' . INVERSIONESJL22_SITE_MY_INVOICE_ACTION . '"]:focus-visible{outline:2px solid #4a4959 !important;outline-offset:2px !important;}'
	);

	wp_register_script( 'inversionesjl22-my-invoice', '', array(), INVERSIONESJL22_SITE_VERSION, true );
	wp_enqueue_script( 'inversionesjl22-my-invoice' );
	wp_add_inline_script(
		'inversionesjl22-my-invoice',
		'document.querySelectorAll(\'a[href*="action=' . INVERSIONESJL22_SITE_MY_INVOICE_ACTION . '"]\').forEach(function(a){a.target="_blank";a.rel="noopener";});'
	);
}

/*
 * ---------------------------------------------------------------------------------------------
 * The customers' endpoint
 * ---------------------------------------------------------------------------------------------
 */

add_action( 'wp_ajax_' . INVERSIONESJL22_SITE_MY_INVOICE_ACTION, 'inversionesjl22_site_my_invoice_endpoint' );
add_action( 'wp_ajax_nopriv_' . INVERSIONESJL22_SITE_MY_INVOICE_ACTION, 'inversionesjl22_site_my_invoice_endpoint' );

/**
 * admin-ajax.php?action=inversionesjl22_my_invoice&order_id=N&_wpnonce=…: the PDF inline for the
 * order's owner, else 403 (a visitor without a session too: never the PDF). Never cacheable: no-
 * cache headers on every answer, and admin-ajax.php is not a page Cloudflare caches for visitors.
 */
function inversionesjl22_site_my_invoice_endpoint(): void {
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow' );

	$order_id = isset( $_GET['order_id'] ) && is_scalar( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0;
	if ( ! is_user_logged_in() || ! $order_id ) {
		inversionesjl22_site_invoice_deny( 403 );
	}
	if ( ! check_ajax_referer( INVERSIONESJL22_SITE_MY_INVOICE_ACTION . '-' . $order_id, '_wpnonce', false ) ) {
		inversionesjl22_site_invoice_deny( 403 );
	}
	$order = wc_get_order( $order_id );
	if ( ! inversionesjl22_site_customer_can_get_invoice( $order ) ) {
		inversionesjl22_site_invoice_deny( 403 );
	}

	$pdf = inversionesjl22_site_invoice_pdf( array( $order_id ) );

	while ( ob_get_level() > 0 ) {
		ob_end_clean();
	}
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: inline; filename="' . rawurlencode( sanitize_file_name( 'factura-' . ltrim( (string) $order->get_order_number(), '#' ) . '.pdf' ) ) . '"' );
	header( 'Content-Length: ' . strlen( $pdf ) );
	echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the PDF's bytes
	exit;
}
