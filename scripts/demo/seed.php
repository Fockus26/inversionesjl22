<?php
/**
 * Demo data for docker-compose.demo.yml (scripts/demo-up.sh runs it with `wp eval-file`).
 * Everything here is invented: people, phones, cédulas and addresses. Emails are @example.com.
 * The shipping method titles are the store's own (getPickUpShippingMethods()), because the
 * payment status and the shipping report key on them.
 */

defined( 'ABSPATH' ) || exit;

$products = array(
	array( 'Audífonos inalámbricos', 'Audio', 89900 ),
	array( 'Parlante portátil', 'Audio', 129900 ),
	array( 'Cargador USB-C 30 W', 'Accesorios', 45900 ),
	array( 'Cable USB-C 2 m', 'Accesorios', 19900 ),
	array( 'Reloj deportivo', 'Relojes', 159900 ),
	array( 'Funda para celular', 'Accesorios', 24900 ),
	array( 'Lámpara de escritorio LED', 'Hogar', 69900 ),
	array( 'Termo de acero 1 L', 'Hogar', 54900 ),
);

$ids = array();
foreach ( $products as $p ) {
	$term = term_exists( $p[1], 'product_cat' );
	if ( ! $term ) {
		$term = wp_insert_term( $p[1], 'product_cat' );
	}
	$product = new WC_Product_Simple();
	$product->set_name( $p[0] );
	$product->set_regular_price( (string) $p[2] );
	$product->set_category_ids( array( (int) $term['term_id'] ) );
	$product->set_manage_stock( true );
	$product->set_stock_quantity( 50 );
	$product->save();
	// The SKU generator runs on the product form's save; here it is the same pattern by hand.
	$product->set_sku( strtoupper( remove_accents( $p[1] ) ) . '-' . $product->get_id() );
	$product->save();
	$ids[] = $product->get_id();
}

$people = array(
	array( 'Valentina', 'Ríos', 'Cúcuta', 'La Riviera' ),
	array( 'Andrés', 'Pabón', 'Cúcuta', 'Caobos' ),
	array( 'Camila', 'Duarte', 'Villa del Rosario', 'Centro' ),
	array( 'Sebastián', 'Mora', 'Los Patios', 'La Sabana' ),
	array( 'Laura', 'Contreras', 'Cúcuta', 'Prados del Este' ),
	array( 'Mateo', 'Villamizar', 'Cúcuta', 'Quinta Oriental' ),
	array( 'Daniela', 'Ortega', 'Villa del Rosario', 'Lomitas' ),
	array( 'Julián', 'Peñaranda', 'Cúcuta', 'El Contento' ),
	array( 'Sara', 'Galvis', 'Los Patios', 'Pórtico' ),
	array( 'Felipe', 'Jaimes', 'Cúcuta', 'Colsag' ),
	array( 'Mariana', 'Suárez', 'Cúcuta', 'Ceiba II' ),
	array( 'Tomás', 'Lizcano', 'Villa del Rosario', 'Santa Bárbara' ),
);

$shipping = array(
	array( 'DOMICILIO CUCUTA', 8000 ),
	array( 'CONTRA ENTREGA EFECTIVO', 10000 ),
	array( 'DOMICILIO SAN CRISTOBAL', 15000 ),
	array( 'ENTREGAS TEALCA', 12000 ),
);

// Index 0 is the oldest order; the list in wp-admin shows the newest (the last ones) first.
$statuses = array(
	'completed', 'completed', 'cancelled', 'impressed', 'completed', 'reserved',
	'impressed', 'on-hold', 'processing', 'impressed', 'processing', 'processing',
);

foreach ( $people as $i => $person ) {
	$order = wc_create_order();
	$order->set_currency( 'COP' );
	$picks = array( $ids[ $i % count( $ids ) ], $ids[ ( $i * 3 + 1 ) % count( $ids ) ] );
	foreach ( array_unique( $picks ) as $n => $pid ) {
		$order->add_product( wc_get_product( $pid ), 1 + ( ( $i + $n ) % 2 ) );
	}
	$address = array(
		'first_name' => $person[0],
		'last_name'  => $person[1],
		'email'      => strtolower( remove_accents( $person[0] . '.' . $person[1] ) ) . '@example.com',
		'phone'      => '300' . str_pad( (string) ( 1000000 + $i * 7919 ), 7, '0', STR_PAD_LEFT ),
		'address_1'  => 'Calle ' . ( 10 + $i ) . ' # ' . ( 3 + $i ) . '-' . ( 20 + $i ),
		'city'       => $person[2],
		'state'      => 'NSA',
		'country'    => 'CO',
	);
	$order->set_address( $address, 'billing' );
	$order->set_address( $address, 'shipping' );
	$order->update_meta_data( '_billing_cedula', (string) ( 1090000000 + $i * 104729 ) );
	$order->update_meta_data( '_billing_barrio', $person[3] );

	$ship = $shipping[ $i % count( $shipping ) ];
	$rate = new WC_Shipping_Rate( 'flat_rate:' . ( $i % 4 + 1 ), $ship[0], $ship[1], array(), 'flat_rate' );
	$item = new WC_Order_Item_Shipping();
	$item->set_shipping_rate( $rate );
	$order->add_item( $item );

	$order->set_payment_method( 'cod' );
	$order->set_payment_method_title( 'Pago contra entrega' );
	$order->calculate_totals();
	$order->set_date_created( time() - ( count( $people ) - $i ) * 5400 );
	$order->set_status( $statuses[ $i ] );
	$order->save();
	// Estado de pago, written as payment-status.php writes it (post meta: _currency is an
	// internal key for WC_Data). Half paid, half to collect on delivery (the order total).
	update_post_meta( $order->get_id(), '_payment-status', $i % 2 ? 'pending' : 'paid' );
	if ( $i % 2 ) {
		update_post_meta( $order->get_id(), '_amount', (float) $order->get_total() );
		update_post_meta( $order->get_id(), '_currency', 'pesos' );
	}
}

update_option( 'blogdescription', 'Copia de demostración con datos inventados' );
echo 'Demo: ' . count( $ids ) . ' productos y ' . count( $people ) . " pedidos inventados.\n";
