<?php
/**
 * Preview of the plugin's own PDFs (WooCommerce › Documentos): the registry of the documents the
 * engine makes and the endpoint that renders one with the settings of the form, saved or not.
 *
 * The preview is the real document: the same generator (invoice-pdf.php, shipping-report/pdf.php)
 * with the paper, typeface and weight of the request in place of the saved ones
 * (inversionesjl22_site_documents_override()). Nothing it does leaves a trace: it does not mark the
 * invoice as created for Mi cuenta, writes no order note or status, sends no email and saves no
 * configuration. Dompdf's work files go where the real documents' do (uploads/inversionesjl22-site/,
 * with an index.php), and the PDF itself is only streamed back.
 *
 * Add a document: filter 'inversionesjl22_site_pdf_documents' (or add it to the array below) with
 * slug => array( 'label', 'description', 'needs_order', 'render' ). 'render' receives the request's
 * arguments (order_id) and returns the PDF as a string, or a WP_Error with a message for the user.
 * The select of the screen shows it with no other change.
 */

defined( 'ABSPATH' ) || exit;

const INVERSIONESJL22_SITE_PREVIEW_ACTION = 'inversionesjl22_site_preview_pdf';

/**
 * The documents the engine makes, slug => definition.
 *
 * @return array<string, array{label: string, description: string, needs_order: bool, render: callable}>
 */
function inversionesjl22_site_pdf_documents(): array {
	$documents = array(
		'invoice'         => array(
			'label'       => __( 'Factura', 'inversionesjl22-site' ),
			'description' => __( 'La factura de un pedido, como la imprime bodega.', 'inversionesjl22-site' ),
			'needs_order' => true,
			'render'      => 'inversionesjl22_site_preview_invoice',
		),
		'shipping-report' => array(
			'label'       => __( 'Informe de envíos', 'inversionesjl22-site' ),
			'description' => __( 'El informe del último día con envíos completados.', 'inversionesjl22-site' ),
			'needs_order' => false,
			'render'      => 'inversionesjl22_site_preview_shipping_report',
		),
	);

	/**
	 * Filters the PDF documents listed in WooCommerce › Documentos' preview.
	 *
	 * @param array $documents slug => array( label, description, needs_order, render ).
	 */
	$filtered = apply_filters( 'inversionesjl22_site_pdf_documents', $documents );
	$valid    = array();
	foreach ( (array) $filtered as $slug => $document ) {
		if ( is_string( $slug ) && sanitize_key( $slug ) === $slug && is_array( $document ) && ! empty( $document['label'] ) && isset( $document['render'] ) && is_callable( $document['render'] ) ) {
			$valid[ $slug ] = $document + array(
				'description' => '',
				'needs_order' => false,
			);
		}
	}
	return $valid;
}

/*
 * ---------------------------------------------------------------------------------------------
 * The sample data: real, never invented
 * ---------------------------------------------------------------------------------------------
 */

/**
 * The order the invoice preview uses: the one asked for (if the user may get its invoice) or the
 * most recent one the user may see. Null when there is none.
 */
function inversionesjl22_site_preview_order( int $order_id ): ?WC_Order {
	if ( $order_id > 0 ) {
		$order = wc_get_order( $order_id );
		return $order instanceof WC_Order && inversionesjl22_site_user_can_get_invoice( $order ) ? $order : null;
	}
	$candidates = wc_get_orders(
		array(
			'type'    => 'shop_order',
			'status'  => array_keys( wc_get_order_statuses() ),
			'limit'   => 20,
			'orderby' => 'date',
			'order'   => 'DESC',
		)
	);
	foreach ( $candidates as $order ) {
		if ( inversionesjl22_site_user_can_get_invoice( $order ) ) {
			return $order;
		}
	}
	return null;
}

/**
 * @param array<string, mixed> $args
 * @return string|WP_Error
 */
function inversionesjl22_site_preview_invoice( array $args ) {
	$requested = (int) ( $args['order_id'] ?? 0 );
	$order     = inversionesjl22_site_preview_order( $requested );
	if ( ! $order ) {
		return new WP_Error(
			'no_data',
			$requested > 0
				/* translators: %d: order number the user typed */
				? sprintf( __( 'No hay un pedido %d que puedas ver. Revisa el número o déjalo vacío para usar el más reciente.', 'inversionesjl22-site' ), $requested )
				: __( 'Todavía no hay pedidos para mostrar como ejemplo. La factura aparecerá aquí cuando exista el primero.', 'inversionesjl22-site' )
		);
	}
	// Not inversionesjl22_site_invoice_mark_created(): that is what offers it to the customer.
	return inversionesjl22_site_invoice_pdf( array( $order->get_id() ) );
}

/**
 * The sample of the shipping report: the most recent day with completed orders shipped to a
 * country the report covers, as array( country code, Y-m-d, order ids ), or null. It reads the 30
 * newest completed orders (an indexed read) instead of running the report's own query, which scans
 * every order's meta and takes minutes on a shop this size; the rows are those the report would list
 * for that day and country. Kept ten minutes in a transient (ids and a date: no personal data).
 *
 * @return array{0: string, 1: string, 2: int[]}|null
 */
function inversionesjl22_site_preview_shipping_sample(): ?array {
	$key    = 'inversionesjl22_site_preview_shipping';
	$cached = get_transient( $key );
	if ( is_array( $cached ) ) {
		return $cached ? $cached : null;
	}
	$sample = array();
	$first  = null;
	foreach (
		wc_get_orders(
			array(
				'type'    => 'shop_order',
				'status'  => 'completed',
				'limit'   => 30,
				'orderby' => 'date',
				'order'   => 'DESC',
			)
		) as $order
	) {
		$country   = (string) $order->get_shipping_country();
		$completed = $order->get_date_completed();
		if ( ! in_array( $country, array( 'VE', 'CO' ), true ) || ! $completed || ! $order->get_items( 'shipping' ) ) {
			continue;
		}
		$day = $completed->date( 'Y-m-d' );
		if ( null === $first ) {
			$first = array( $country, $day );
		}
		if ( array( $country, $day ) === $first ) {
			$sample[] = $order->get_id();
		}
	}
	$sample = $first ? array( $first[0], $first[1], $sample ) : array();
	set_transient( $key, $sample, 10 * MINUTE_IN_SECONDS );
	return $sample ? $sample : null;
}

/**
 * @param array<string, mixed> $args
 * @return string|WP_Error
 */
function inversionesjl22_site_preview_shipping_report( array $args ) {
	$sample = inversionesjl22_site_preview_shipping_sample();
	if ( ! $sample ) {
		return new WP_Error( 'no_data', __( 'Todavía no hay pedidos completados con envío. El informe aparecerá aquí cuando haya uno.', 'inversionesjl22-site' ) );
	}
	list( $code, $day, $ids ) = $sample;
	$orders                   = array_filter( array_map( 'wc_get_order', $ids ) );
	$countries                = array(
		'VE' => 'Venezuela',
		'CO' => 'Colombia',
	);
	return (string) inversionesjl22_site_shipping_report_render(
		array(
			'company'   => 'Todo el país',
			'startDate' => $day,
			'endDate'   => $day,
			'orders'    => array_values( $orders ),
			'country'   => $countries[ $code ] ?? $code,
		)
	)->output();
}

/*
 * ---------------------------------------------------------------------------------------------
 * The endpoint
 * ---------------------------------------------------------------------------------------------
 */

add_action( 'admin_post_' . INVERSIONESJL22_SITE_PREVIEW_ACTION, 'inversionesjl22_site_preview_endpoint' );

/**
 * The URL of a preview for the screen: the settings are the form's (paper_size, font, font_weight)
 * and the rest the request's (doc, order_id); anything missing is the saved configuration.
 *
 * @param array<string, string|int> $args
 */
function inversionesjl22_site_preview_url( array $args = array() ): string {
	return add_query_arg(
		array_filter( $args, static fn( $value ) => '' !== $value && 0 !== $value ) + array(
			'action'   => INVERSIONESJL22_SITE_PREVIEW_ACTION,
			'_wpnonce' => wp_create_nonce( INVERSIONESJL22_SITE_PREVIEW_ACTION ),
		),
		admin_url( 'admin-post.php' )
	);
}

/**
 * Says no: JSON for the screen's script (?ajax=1), the usual wp-admin page otherwise.
 */
function inversionesjl22_site_preview_fail( string $message, int $code ): void {
	nocache_headers();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks the answer's shape.
	if ( isset( $_GET['ajax'] ) ) {
		wp_send_json_error( array( 'message' => $message ), $code );
	}
	wp_die( esc_html( $message ), esc_html__( 'Previsualización', 'inversionesjl22-site' ), array( 'response' => $code ) );
}

/**
 * admin-post.php?action=inversionesjl22_site_preview_pdf&doc=…&_wpnonce=…: the PDF inline. Same
 * capability as the screen (manage_options) and its own nonce; every parameter is checked against
 * the lists the screen offers. Logged-in users only (no admin_post_nopriv_).
 */
function inversionesjl22_site_preview_endpoint(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		inversionesjl22_site_preview_fail( __( 'No tienes permiso para previsualizar los documentos.', 'inversionesjl22-site' ), 403 );
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified on the next line.
	$nonce = isset( $_GET['_wpnonce'] ) && is_string( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, INVERSIONESJL22_SITE_PREVIEW_ACTION ) ) {
		inversionesjl22_site_preview_fail( __( 'El enlace de la previsualización caducó. Recarga la pantalla e inténtalo de nuevo.', 'inversionesjl22-site' ), 403 );
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- verified above.
	$get = static function ( string $key ): string {
		return isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ? sanitize_key( wp_unslash( $_GET[ $key ] ) ) : '';
	};
	$doc_slug = $get( 'doc' );
	$order_id = isset( $_GET['order_id'] ) && is_string( $_GET['order_id'] ) && ctype_digit( wp_unslash( $_GET['order_id'] ) ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0;
	$has      = static function ( string $key ): bool {
		return isset( $_GET[ $key ] ) && '' !== $_GET[ $key ];
	};
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$documents = inversionesjl22_site_pdf_documents();
	if ( ! isset( $documents[ $doc_slug ] ) ) {
		inversionesjl22_site_preview_fail( __( 'Elige un documento de la lista.', 'inversionesjl22-site' ), 400 );
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified above.
	if ( isset( $_GET['order_id'] ) && '' !== $_GET['order_id'] && 0 === $order_id ) {
		inversionesjl22_site_preview_fail( __( 'El número de pedido debe ser un número entero.', 'inversionesjl22-site' ), 400 );
	}

	// What the form sends replaces the saved value; each one must be in its list (the same ones
	// the save checks) or the request is refused.
	$override = array();
	if ( $has( 'paper_size' ) ) {
		$size = inversionesjl22_site_documents_from_form( $get( 'paper_size' ) );
		if ( '' === $size ) {
			inversionesjl22_site_preview_fail( __( 'Elige uno de los tamaños de papel de la lista.', 'inversionesjl22-site' ), 400 );
		}
		$override['paper_size'] = $size;
	}
	if ( isset( $_GET['font'], $_GET['font_weight'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$text = inversionesjl22_site_documents_text_from_form( $get( 'font' ), $get( 'font_weight' ) );
		if ( null === $text ) {
			inversionesjl22_site_preview_fail( __( 'Elige la tipografía y el grosor de las listas.', 'inversionesjl22-site' ), 400 );
		}
		$override += $text;
	}

	nocache_headers();
	$pdf = null;
	try {
		inversionesjl22_site_documents_override( $override ? $override : null );
		$pdf = call_user_func( $documents[ $doc_slug ]['render'], array( 'order_id' => $order_id ) );
	} catch ( \Throwable $e ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( 'Previsualización del PDF «' . $doc_slug . '»: ' . $e->getMessage(), array( 'source' => 'inversionesjl22-site' ) );
		}
		$pdf = new WP_Error( 'failed', __( 'No se pudo generar la previsualización. Inténtalo de nuevo; si sigue fallando, avisa a quien mantiene el sitio.', 'inversionesjl22-site' ) );
	} finally {
		inversionesjl22_site_documents_override( null );
	}

	if ( is_wp_error( $pdf ) ) {
		inversionesjl22_site_preview_fail( $pdf->get_error_message(), 'no_data' === $pdf->get_error_code() ? 404 : 500 );
	}
	if ( ! is_string( $pdf ) || 0 !== strpos( $pdf, '%PDF' ) ) {
		inversionesjl22_site_preview_fail( __( 'No se pudo generar la previsualización.', 'inversionesjl22-site' ), 500 );
	}

	// Whatever another plugin printed into an open buffer would go before %PDF-.
	while ( ob_get_level() > 0 ) {
		ob_end_clean();
	}
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: inline; filename="' . rawurlencode( sanitize_file_name( 'previsualizacion-' . $doc_slug . '.pdf' ) ) . '"' );
	header( 'Content-Length: ' . strlen( $pdf ) );
	header( 'X-Content-Type-Options: nosniff' );
	echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the PDF's bytes
	exit;
}
