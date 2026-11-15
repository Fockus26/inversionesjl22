<?php
/**
 * Combining orders: checks that a set of orders can become one, and makes it.
 *
 * Loaded only when someone combines (combined-orders.php requires it from the bulk action). It
 * replaces "Ultimate Order Combination" with what the shop used of it:
 *   - two or more orders of the same customer, the same currency and the same status, none of
 *     them refunded or combined before; the new order keeps that status (César, 2026-11-14);
 *   - the new order gets the product lines (the same product, variation and visible line meta
 *     become one line: quantities and the prices paid added up), the coupons and the fees, the
 *     customer, the billing and shipping address, the cédula and the barrio of the most recent
 *     order, and one shipping line: the first one found, as combinedOrderFields() did, with its
 *     name in the shipping company field (the shipping report reads it);
 *   - the stock moves with the lines: what the originals reduced is now the new order's
 *     (_reduced_stock on its lines), so it is never reduced twice, and cancelling the new order
 *     gives it back once; the same for the products' sales and the coupons' uses, which the
 *     originals already counted;
 *   - the payment status of cash on delivery (Pagado / Por cobrar, payment-status.php) when all
 *     the originals have the same one, with the amounts to collect added up;
 *   - the originals get a note and the link to the new order, and go to the status "Combinado"
 *     (César, 2026-11-14; the old plugin sent them to the trash), which they never leave
 *     (combined-orders.php).
 * Setting the new order's status runs WooCommerce's transition once, with every email off; the
 * stock, the sales and the coupons are marked as counted first, so it counts nothing again. The
 * shop's own marks (printed, QR) are not set by a status change, so none is made: an order
 * combined as Impreso still has to be printed (its invoice is a new one).
 * No email to the customer. The admins' "Nuevo pedido" email is sent once, as the old plugin did.
 * Works with orders as posts and with HPOS: only WC_Order's API.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Statuses whose orders are never combined: there is nothing left to deliver.
 *
 * @return string[]
 */
function inversionesjl22_site_combine_closed_statuses(): array {
	return array_merge( array( 'cancelled', 'refunded', 'failed', 'trash', 'checkout-draft' ), inversionesjl22_site_combined_statuses() );
}

/**
 * "#101, #102 y #103".
 *
 * @param WC_Order[] $orders
 */
function inversionesjl22_site_combine_numbers( array $orders ): string {
	$numbers = array();
	foreach ( $orders as $order ) {
		$numbers[] = '#' . $order->get_order_number();
	}
	$last = array_pop( $numbers );
	return $numbers ? implode( ', ', $numbers ) . ' ' . __( 'y', 'inversionesjl22-site' ) . ' ' . $last : (string) $last;
}

/**
 * Whether two orders are the same customer's: the same account, or (guest orders) the same
 * billing email or phone.
 */
function inversionesjl22_site_combine_same_customer( WC_Order $a, WC_Order $b ): bool {
	if ( $a->get_customer_id() && $b->get_customer_id() ) {
		return $a->get_customer_id() === $b->get_customer_id();
	}
	$email_a = strtolower( trim( (string) $a->get_billing_email() ) );
	if ( '' !== $email_a && strtolower( trim( (string) $b->get_billing_email() ) ) === $email_a ) {
		return true;
	}
	$phone_a = preg_replace( '/\D+/', '', (string) $a->get_billing_phone() );
	return '' !== $phone_a && preg_replace( '/\D+/', '', (string) $b->get_billing_phone() ) === $phone_a;
}

/**
 * The orders for $ids, or why they cannot be combined.
 *
 * @param int[] $ids
 * @return WC_Order[]|WP_Error Oldest first.
 */
function inversionesjl22_site_combine_check( array $ids ) {
	$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	if ( count( $ids ) < 2 ) {
		return new WP_Error( 'few', __( 'Elige al menos dos pedidos para combinar.', 'inversionesjl22-site' ) );
	}

	$orders = array();
	foreach ( $ids as $id ) {
		$order = wc_get_order( $id );
		if ( ! $order instanceof WC_Order || 'shop_order' !== $order->get_type() ) {
			/* translators: %d: order id */
			return new WP_Error( 'missing', sprintf( __( 'El pedido #%d ya no existe.', 'inversionesjl22-site' ), $id ) );
		}
		$orders[] = $order;
	}
	usort(
		$orders,
		static function ( WC_Order $a, WC_Order $b ) {
			return $a->get_id() <=> $b->get_id();
		}
	);

	$first   = $orders[0];
	$status  = $first->get_status();
	$ruleset = function_exists( 'getUserPermissions' ) ? getUserPermissions() : null;
	foreach ( $orders as $order ) {
		$number = $order->get_order_number();
		// Ours, or the old plugin's (cloned_from on its originals, wuoc_combined on the ones it
		// left half combined).
		if ( inversionesjl22_site_is_combined( $order ) || $order->get_meta( INVERSIONESJL22_SITE_COMBINED_INTO ) || $order->get_meta( 'cloned_from' ) || $order->get_meta( 'wuoc_combined' ) ) {
			/* translators: %s: order number */
			return new WP_Error( 'combined', sprintf( __( 'El pedido #%s ya se combinó en otro.', 'inversionesjl22-site' ), $number ) );
		}
		if ( $order->get_status() !== $status ) {
			return new WP_Error(
				'status',
				sprintf(
					/* translators: 1: order number, 2: its status, 3: order number, 4: its status */
					__( 'Solo se combinan pedidos del mismo estado: #%1$s está en %2$s y #%3$s en %4$s.', 'inversionesjl22-site' ),
					$first->get_order_number(),
					wc_get_order_status_name( $status ),
					$number,
					wc_get_order_status_name( $order->get_status() )
				)
			);
		}
		if ( $order->get_currency() !== $first->get_currency() ) {
			return new WP_Error(
				'currency',
				sprintf(
					/* translators: 1: order number, 2: currency, 3: order number, 4: currency */
					__( 'Los pedidos no están en la misma moneda: #%1$s en %2$s y #%3$s en %4$s.', 'inversionesjl22-site' ),
					$first->get_order_number(),
					$first->get_currency(),
					$number,
					$order->get_currency()
				)
			);
		}
		if ( ! inversionesjl22_site_combine_same_customer( $first, $order ) ) {
			/* translators: 1: order number, 2: order number */
			return new WP_Error( 'customer', sprintf( __( 'Los pedidos #%1$s y #%2$s no son del mismo cliente (ni la cuenta, ni el correo, ni el teléfono coinciden).', 'inversionesjl22-site' ), $first->get_order_number(), $number ) );
		}
		if ( $order->get_total_refunded() > 0 ) {
			/* translators: %s: order number */
			return new WP_Error( 'refunded', sprintf( __( 'El pedido #%s tiene reembolsos: no se combina.', 'inversionesjl22-site' ), $number ) );
		}
	}

	if ( in_array( $status, inversionesjl22_site_combine_closed_statuses(), true ) ) {
		/* translators: %s: status name */
		return new WP_Error( 'closed', sprintf( __( 'Los pedidos en estado %s no se combinan.', 'inversionesjl22-site' ), wc_get_order_status_name( $status ) ) );
	}
	// A role combines only the orders it sees (storage_manager: not on hold or reserved).
	if ( null !== $ruleset && empty( $ruleset[ $status ] ) ) {
		/* translators: %s: status name */
		return new WP_Error( 'role', sprintf( __( 'Tu rol no trabaja con pedidos en estado %s.', 'inversionesjl22-site' ), wc_get_order_status_name( $status ) ) );
	}

	return $orders;
}

/**
 * Adds up a line's taxes ('total' and 'subtotal', by rate id) into $sum.
 */
function inversionesjl22_site_combine_add_taxes( array $sum, array $taxes ): array {
	foreach ( array( 'total', 'subtotal' ) as $part ) {
		foreach ( (array) ( $taxes[ $part ] ?? array() ) as $rate => $amount ) {
			$sum[ $part ][ $rate ] = ( $sum[ $part ][ $rate ] ?? 0 ) + (float) $amount;
		}
	}
	return $sum;
}

/**
 * Takes (or, with $take false, releases) a lock on each order id, so two clicks (or two people)
 * never combine the same orders. False when one of them is already being combined. An INSERT
 * IGNORE on the options table: the row exists or not, atomically (add_option() checks first and
 * inserts after, and two requests can both pass that check).
 *
 * @param int[] $ids
 */
function inversionesjl22_site_combine_lock( array $ids, bool $take ): bool {
	global $wpdb;
	$taken = array();
	foreach ( $ids as $id ) {
		$key = 'inversionesjl22_site_combining_' . (int) $id;
		if ( ! $take ) {
			delete_option( $key );
			continue;
		}
		$insert = static function () use ( $wpdb, $key ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a lock: it must not be cached.
			return 1 === (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", $key, (string) time() ) );
		};
		$got = $insert();
		if ( ! $got ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$since = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) );
			if ( $since && $since < time() - 5 * MINUTE_IN_SECONDS ) {
				delete_option( $key ); // Left by a request that died.
				$got = $insert();
			}
		}
		if ( ! $got ) {
			foreach ( $taken as $key_taken ) {
				delete_option( $key_taken );
			}
			return false;
		}
		$taken[] = $key;
	}
	return true;
}

/**
 * Combines the orders of $ids into a new one.
 *
 * @param int[] $ids
 * @return WC_Order|WP_Error The new order.
 */
function inversionesjl22_site_combine_orders( array $ids ) {
	$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	sort( $ids );
	// The lock before the checks: a second request that waited for the first one finds the
	// originals already combined, instead of combining them again.
	if ( count( $ids ) > 1 && ! inversionesjl22_site_combine_lock( $ids, true ) ) {
		return new WP_Error( 'busy', __( 'Esos pedidos ya se están combinando. Espera un momento y recarga la lista.', 'inversionesjl22-site' ) );
	}
	$orders = inversionesjl22_site_combine_check( $ids );
	if ( is_wp_error( $orders ) ) {
		inversionesjl22_site_combine_lock( $ids, false );
		return $orders;
	}

	$order = new WC_Order();
	try {
		inversionesjl22_site_combine_build( $order, $orders );
		inversionesjl22_site_combine_close_originals( $order, $orders );
	} catch ( Throwable $e ) {
		inversionesjl22_site_combining( false );
		inversionesjl22_site_combine_lock( $ids, false );
		if ( $order->get_meta( '_inversionesjl22_site_combined_done' ) ) {
			/* translators: 1: order number, 2: technical error */
			return new WP_Error( 'partial', sprintf( __( 'Se creó el pedido #%1$s, pero no todos los originales pasaron a Combinado (%2$s). Revisa los originales.', 'inversionesjl22-site' ), $order->get_order_number(), $e->getMessage() ) );
		}
		// The originals are touched last: if the new order is half made, it goes, and they stay.
		if ( $order->get_id() ) {
			$order->delete( true );
		}
		/* translators: %s: technical error */
		return new WP_Error( 'failed', sprintf( __( 'No se pudo combinar: %s. Los pedidos originales no cambiaron.', 'inversionesjl22-site' ), $e->getMessage() ) );
	}
	inversionesjl22_site_combine_lock( $ids, false );
	return $order;
}

/**
 * Makes the new order ($order, not saved yet) in the originals' status, without emails to the
 * customer and without counting the stock, the sales or the coupons again.
 *
 * @param WC_Order[] $orders Oldest first.
 */
function inversionesjl22_site_combine_build( WC_Order $order, array $orders ): void {
	$latest = end( $orders );
	$status = $latest->get_status();

	$order->set_created_via( 'inversionesjl22-combine' );
	$order->set_order_key( wc_generate_order_key() );
	$order->set_currency( $latest->get_currency() );
	$order->set_prices_include_tax( $latest->get_prices_include_tax() );
	$order->set_customer_id( $latest->get_customer_id() );
	$order->set_address( $latest->get_address( 'billing' ), 'billing' );
	$order->set_address( $latest->get_address( 'shipping' ), 'shipping' );
	$order->set_payment_method( $latest->get_payment_method() );
	$order->set_payment_method_title( $latest->get_payment_method_title() );

	$notes = array();
	foreach ( $orders as $original ) {
		$note = trim( (string) $original->get_customer_note() );
		if ( '' !== $note && ! in_array( $note, $notes, true ) ) {
			$notes[] = $note;
		}
	}
	$order->set_customer_note( implode( "\n", $notes ) );

	// Cédula and barrio: under both keys, as the checkout and the old snippet keep them. The most
	// recent order first; an older one fills what it lacks.
	foreach ( array( 'billing_cedula', 'billing_barrio' ) as $field ) {
		foreach ( array_reverse( $orders ) as $original ) {
			$value = $original->get_meta( '_' . $field );
			$value = '' !== (string) $value ? $value : $original->get_meta( $field );
			if ( '' !== (string) $value ) {
				$order->update_meta_data( '_' . $field, $value );
				$order->update_meta_data( $field, $value );
				break;
			}
		}
	}

	// The other order meta the old plugin carried over that matter here: the multi-currency rates
	// (WooCommerce Multi Currency) and the VAT exemption. Not the Mercado Pago payment records:
	// they belong to each original's payment.
	foreach ( array( 'wmc_order_info', 'is_vat_exempt' ) as $key ) {
		$value = $latest->get_meta( $key );
		if ( '' !== $value && null !== $value ) {
			$order->update_meta_data( $key, $value );
		}
	}

	// Cash on delivery: the payment status (payment-status.php) when every original has the same
	// one; "Por cobrar" adds up the amounts to collect when they are in the same currency. Mixed
	// or missing: left empty, so the team sets it before moving the order on (order-transitions.php
	// asks for it).
	// Read and written as payment-status.php does, as post meta: through WC_Order, '_currency'
	// would be the order's own currency (set_currency('pesos') throws).
	$payment = array();
	foreach ( $orders as $original ) {
		$payment[] = (string) get_post_meta( $original->get_id(), '_payment-status', true );
	}
	$payment      = array_values( array_unique( $payment ) );
	$payment_meta = array();
	if ( 1 === count( $payment ) && '' !== $payment[0] ) {
		$payment_meta['_payment-status'] = $payment[0];
		if ( 'pending' === $payment[0] ) {
			$amount     = 0.0;
			$currencies = array();
			foreach ( $orders as $original ) {
				$amount      += (float) get_post_meta( $original->get_id(), '_amount', true );
				$currencies[] = (string) get_post_meta( $original->get_id(), '_currency', true );
			}
			$currencies = array_values( array_unique( $currencies ) );
			if ( $amount > 0 && 1 === count( $currencies ) && '' !== $currencies[0] ) {
				$payment_meta['_amount']   = $amount;
				$payment_meta['_currency'] = $currencies[0];
			}
		}
	}

	// Product lines, one per product + variation + visible meta.
	$lines = array();
	foreach ( $orders as $original ) {
		foreach ( $original->get_items( 'line_item' ) as $item ) {
			$meta = array();
			foreach ( $item->get_meta_data() as $entry ) {
				$data = $entry->get_data();
				if ( '' !== (string) $data['key'] && '_' !== $data['key'][0] ) {
					$meta[ $data['key'] ] = $data['value'];
				}
			}
			ksort( $meta );
			$key = $item->get_product_id() . '|' . $item->get_variation_id() . '|' . md5( maybe_serialize( $meta ) );
			if ( ! isset( $lines[ $key ] ) ) {
				$lines[ $key ] = array(
					'first'        => $item,
					'meta'         => $meta,
					'quantity'     => 0,
					'subtotal'     => 0,
					'total'        => 0,
					'subtotal_tax' => 0,
					'total_tax'    => 0,
					'taxes'        => array( 'total' => array(), 'subtotal' => array() ),
					'reduced'      => 0,
				);
			}
			$line                  = &$lines[ $key ];
			$line['quantity']     += (float) $item->get_quantity();
			$line['subtotal']     += (float) $item->get_subtotal();
			$line['total']        += (float) $item->get_total();
			$line['subtotal_tax'] += (float) $item->get_subtotal_tax();
			$line['total_tax']    += (float) $item->get_total_tax();
			$line['taxes']         = inversionesjl22_site_combine_add_taxes( $line['taxes'], $item->get_taxes() );
			$line['reduced']      += (float) $item->get_meta( '_reduced_stock' );
			unset( $line );
		}
	}
	$reduced = false;
	foreach ( $lines as $line ) {
		$first = $line['first'];
		$item  = new WC_Order_Item_Product();
		$item->set_props(
			array(
				'name'         => $first->get_name(),
				'product_id'   => $first->get_product_id(),
				'variation_id' => $first->get_variation_id(),
				'tax_class'    => $first->get_tax_class(),
				'quantity'     => $line['quantity'],
				'subtotal'     => $line['subtotal'],
				'total'        => $line['total'],
				'subtotal_tax' => $line['subtotal_tax'],
				'total_tax'    => $line['total_tax'],
				'taxes'        => $line['taxes'],
			)
		);
		foreach ( $line['meta'] as $meta_key => $meta_value ) {
			$item->add_meta_data( $meta_key, $meta_value, true );
		}
		if ( $line['reduced'] > 0 ) {
			// The stock the originals took is this line's now: cancelling it gives it back once.
			$item->add_meta_data( '_reduced_stock', wc_stock_amount( $line['reduced'] ), true );
			$reduced = true;
		}
		$order->add_item( $item );
	}

	// Fees, one per name + tax class; coupons, one per code.
	$fees    = array();
	$coupons = array();
	foreach ( $orders as $original ) {
		foreach ( $original->get_items( 'fee' ) as $fee ) {
			$key = $fee->get_name() . '|' . $fee->get_tax_class() . '|' . $fee->get_tax_status();
			if ( ! isset( $fees[ $key ] ) ) {
				$fees[ $key ] = array( 'first' => $fee, 'total' => 0, 'total_tax' => 0, 'taxes' => array( 'total' => array() ) );
			}
			$fees[ $key ]['total']     += (float) $fee->get_total();
			$fees[ $key ]['total_tax'] += (float) $fee->get_total_tax();
			$fees[ $key ]['taxes']      = inversionesjl22_site_combine_add_taxes( $fees[ $key ]['taxes'], $fee->get_taxes() );
		}
		foreach ( $original->get_items( 'coupon' ) as $coupon ) {
			$key = wc_format_coupon_code( $coupon->get_code() );
			if ( ! isset( $coupons[ $key ] ) ) {
				$coupons[ $key ] = array( 'first' => $coupon, 'discount' => 0, 'discount_tax' => 0 );
			}
			$coupons[ $key ]['discount']     += (float) $coupon->get_discount();
			$coupons[ $key ]['discount_tax'] += (float) $coupon->get_discount_tax();
		}
	}
	foreach ( $fees as $fee ) {
		$item = new WC_Order_Item_Fee();
		$item->set_props(
			array(
				'name'       => $fee['first']->get_name(),
				'tax_class'  => $fee['first']->get_tax_class(),
				'tax_status' => $fee['first']->get_tax_status(),
				'total'      => $fee['total'],
				'total_tax'  => $fee['total_tax'],
				'taxes'      => $fee['taxes'],
			)
		);
		$order->add_item( $item );
	}
	foreach ( $coupons as $coupon ) {
		$item = new WC_Order_Item_Coupon();
		$item->set_props(
			array(
				'name'         => $coupon['first']->get_name(),
				'code'         => $coupon['first']->get_code(),
				'discount'     => $coupon['discount'],
				'discount_tax' => $coupon['discount_tax'],
			)
		);
		$order->add_item( $item );
	}

	// One shipping line: the first one found, newest order first (as combinedOrderFields()). Its
	// name also goes in the shipping company field, which the shipping report shows.
	foreach ( array_reverse( $orders ) as $original ) {
		$shipping = current( $original->get_items( 'shipping' ) );
		if ( ! $shipping instanceof WC_Order_Item_Shipping ) {
			continue;
		}
		$item = new WC_Order_Item_Shipping();
		$item->set_method_title( $shipping->get_name() );
		$item->set_method_id( $shipping->get_method_id() );
		$item->set_instance_id( $shipping->get_instance_id() );
		$item->set_total( $shipping->get_total() );
		$item->set_taxes( $shipping->get_taxes() );
		$order->add_item( $item );
		$order->set_shipping_company( $shipping->get_name() );
		break;
	}

	$ids = array();
	foreach ( $orders as $original ) {
		$ids[] = $original->get_id();
	}
	$order->update_meta_data( INVERSIONESJL22_SITE_COMBINED_FROM, $ids );
	$order->save();
	foreach ( $payment_meta as $key => $value ) {
		update_post_meta( $order->get_id(), $key, $value );
	}

	// The lines carry their taxes: add them up, do not recompute them with today's rates.
	$order->update_taxes();
	$order->calculate_totals( false );

	// Before the status, what the originals already counted: the move to on-hold, processing or
	// completed must not reduce the stock, add the sales or use the coupons again. On the object
	// (its props) and in the store, so the save of update_status() does not write them back.
	$sales   = false;
	$coupons = false;
	foreach ( $orders as $original ) {
		$store   = $original->get_data_store();
		$sales   = $sales || $store->get_recorded_sales( $original );
		$coupons = $coupons || ( $original->get_items( 'coupon' ) && $store->get_recorded_coupon_usage_counts( $original ) );
	}
	$store = $order->get_data_store();
	if ( $reduced ) {
		$order->set_order_stock_reduced( true );
		$store->set_stock_reduced( $order, true );
	}
	if ( $sales ) {
		$order->set_recorded_sales( true );
		$store->set_recorded_sales( $order, true );
	}
	if ( $coupons ) {
		$store->set_recorded_coupon_usage_counts( $order, true );
	}

	// The status, with the emails off: the customer was told about each order already.
	$mailer = WC()->mailer();
	foreach ( $mailer->get_emails() as $email ) {
		add_filter( 'woocommerce_email_enabled_' . $email->id, '__return_false', PHP_INT_MAX );
	}
	$user = wp_get_current_user();
	$note = sprintf(
		/* translators: 1: order numbers, 2: user name */
		__( 'Pedido combinado a partir de %1$s (por: %2$s).', 'inversionesjl22-site' ),
		inversionesjl22_site_combine_numbers( $orders ),
		$user->exists() ? $user->display_name : __( 'el sistema', 'inversionesjl22-site' )
	);
	if ( $order->get_status() === $status ) {
		$order->add_order_note( $note, 0, true ); // Pendiente de pago: there is no transition to carry it.
	} else {
		$order->update_status( $status, $note, true );
	}
	foreach ( $mailer->get_emails() as $email ) {
		remove_filter( 'woocommerce_email_enabled_' . $email->id, '__return_false', PHP_INT_MAX );
	}

	// The admins' "Nuevo pedido", once, as the old plugin sent it.
	$emails = $mailer->get_emails();
	if ( isset( $emails['WC_Email_New_Order'] ) ) {
		$emails['WC_Email_New_Order']->trigger( $order->get_id(), $order );
	}
}

/**
 * The originals: their stock, sales and coupon uses go to the new order (the marks that say they
 * were counted pass to it, build() set them there), they get the link and a note, and they move
 * to "Combinado", which gives no stock back (WooCommerce does that on cancelled and pending only)
 * and sends no email (none is hooked to it). The guard of combined-orders.php lets this one
 * change through.
 *
 * @param WC_Order[] $orders
 */
function inversionesjl22_site_combine_close_originals( WC_Order $order, array $orders ): void {
	$order->update_meta_data( '_inversionesjl22_site_combined_done', 1 );
	$order->save_meta_data();

	$user = wp_get_current_user();
	inversionesjl22_site_combining( true );
	try {
		foreach ( $orders as $original ) {
			foreach ( $original->get_items( 'line_item' ) as $item ) {
				if ( '' !== (string) $item->get_meta( '_reduced_stock' ) ) {
					$item->delete_meta_data( '_reduced_stock' );
					$item->save();
				}
			}
			// Its stock, its sales and its coupons' uses are the new order's now: deleting or
			// trashing it later must not take them back (WooCommerce does, for a counted order).
			$store = $original->get_data_store();
			$original->set_order_stock_reduced( false );
			$store->set_stock_reduced( $original, false );
			$original->set_recorded_sales( false );
			$store->set_recorded_sales( $original, false );
			$store->set_recorded_coupon_usage_counts( $original, false );
			$original->update_meta_data( INVERSIONESJL22_SITE_COMBINED_INTO, $order->get_id() );
			$original->update_status(
				INVERSIONESJL22_SITE_COMBINED_STATUS,
				sprintf(
					/* translators: 1: order number, 2: user name */
					__( 'Combinado en el pedido #%1$s (por: %2$s): sus productos, su inventario y su cobro pasaron a ese pedido.', 'inversionesjl22-site' ),
					$order->get_order_number(),
					$user->exists() ? $user->display_name : __( 'el sistema', 'inversionesjl22-site' )
				),
				true
			);
		}
	} finally {
		inversionesjl22_site_combining( false );
	}
}
