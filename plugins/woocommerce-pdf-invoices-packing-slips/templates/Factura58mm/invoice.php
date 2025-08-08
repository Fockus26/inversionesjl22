<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>
<?php do_action( 'wpo_wcpdf_before_document', $this->type, $this->order ); ?>


<?php 
	$order = $this->order;
	$order_id = $order->get_id();
	$customer_id = get_post_meta($order->get_id(), '_customer_user', true);
	
	
	$customer = get_user_by('id', $customer_id);
	
	
	
	// $meta = get_post_meta($order->get_id(), '_billing_address_index', true);
	
	// echo $meta;
	
	
	// error_log(print_r($order->get_customer_note(), true));
	
	
	$countries_obj = new WC_Countries();
	$countries_array = $countries_obj->get_countries();
	$country_states_array = $countries_obj->get_states();	
	
	$country_string = $countries_array[get_post_meta($order_id, '_billing_country', true)];
	$state_string = $country_states_array[get_post_meta($order_id, '_billing_country', true)][get_post_meta($order_id, '_billing_state', true)];
	
?>



<?php 
	$order = $this->order;
	$order_id = $order->get_id();
	$customer_id = get_post_meta($order->get_id(), '_customer_user', true);
	
	
	$customer = get_user_by('id', $customer_id);
	
	$shipping_method_title = '';
	foreach( $order->get_items( 'shipping' ) as $item_id => $shipping_item_obj ){
		$shipping_method_title       = $shipping_item_obj->get_method_title();
	}
	
	
	
	// $meta = get_post_meta($order->get_id(), '_billing_address_index', true);
	
	// echo $meta;
?>


<table class="delivery-details">
	<tr>
		<td>
			<table width="100%">
				<tr>
					<td colspan="3">Comprobante de venta # <?php $this->order_number(); ?></td>
				</tr>
				<tr>
					<td colspan="3">Emitido el <?php $this->invoice_date(); ?></td>
				</tr>
			
				<tr>
					<td colspan="3" height="10"></td>
				</tr>
			
				<tr>
					<td colspan="3">
						<table width="100%">
							<tr>
								<td><strong>Cliente</strong></td>
								<td><?php echo strtoupper(get_post_meta($order_id, '_billing_first_name', true) . ' ' . get_post_meta($order_id, '_billing_last_name', true)); ?></td>
							</tr>
							<tr>
								<td><strong>Cedula</strong></td>
								<td><?php echo strtoupper(get_post_meta($order_id, '_billing_cedula', true)); ?></td>
							</tr>				
							<tr>
								<td><strong>Telefono</strong></td>
								<td><?php echo strtoupper(get_post_meta($order_id, '_billing_phone', true)); ?></td>
							</tr>
							<tr>
								<td><strong>Ciudad</strong></td>
								<td><?php echo strtoupper(get_post_meta($order_id, '_billing_city', true)); ?></td>
							</tr>
							<tr>
								<td><strong>Estado</strong></td>
								<td><?php echo strtoupper($state_string); ?></td>
							</tr>
							<tr>
								<td><strong>Barrio</strong></td>
								<td><?php echo strtoupper(get_post_meta($order_id, '_billing_barrio', true)); ?></td>
							</tr>
							<tr>
								<td><strong>Direccion</strong></td>
								<td><?php echo strtoupper(get_post_meta($order_id, '_billing_address_1', true) . '. ' . get_post_meta($order_id, '_billing_address_2', true)); ?></td>
							</tr>
							<tr>
								<td><strong>Pais</strong></td>
								<td><?php echo strtoupper($country_string); ?></td>
							</tr>
							
						</table>
					</td>
				</tr>
				

			
				<tr>
					<td colspan="3" height="10"></td>
				</tr>
			
				<tr>
					<td width="50%"><strong>Producto</strong></td>
					<td width="10%" style="text-align:center;"><strong>Cant</strong></td>
					<td width="40%" style="text-align:right;"><strong>PU</strong></td>
				</tr>
				
				<?php $items = $this->get_order_items(); if( sizeof( $items ) > 0 ) : foreach( $items as $item_id => $item ) : ?>		
					<tr>
						
						<?php
							$product_id = $item['product_id'];
							$product = wc_get_product( $product_id );	

							// error_log(print_r($item, true));
							
						?>
						
						<td>
							<?php
								/*
								$product_factory = new WC_Product_Factory();  
								$product_id = $item['product_id'];
								$product = $product_factory->get_product($product_id);
								
								echo strtoupper($item['name']);
								*/
								
								if($item['variation_id'] == 0){
									echo '<div>' . strtoupper($product->get_name()) . '<div>';
								}
								else{
									
									echo '<div>' . strtoupper($product->get_name()) . '<div>';									
									// error_log(print_r($item['item']->get_meta_data(), true));
									
									foreach ($item['item']->get_meta_data() as $meta_data) 
									{
										$attribute = $meta_data->get_data();
										
										if($attribute['key'] != '_reduced_stock'){
											
											$string_key = substr($attribute['key'], 3);
											$string_key = str_replace("-", " ", $string_key);
											
											echo '<div class="attribute-description"><strong>' . ucwords($string_key) . ':</strong> ' . ucfirst($attribute['value']) . '</div>';
										}
									}
								}
							?>							
						</td>
						<td style="text-align: center;"><?php echo $item['quantity']; ?></td>
						<td style="text-align: right;"><?php echo $item['single_price']; ?></td>
						
					</tr>
				<?php endforeach; endif; ?>	
				
			</table>
		</td>
	</tr>
	
	<tr><td height="10"></td></tr>
	
	<tr>
		<td>
			<table>
				
				<?php foreach( $this->get_woocommerce_totals() as $key => $total ) : ?>
					<?php if($total['label'] == 'Total') { ?>
						<tr>
							<td><strong><?php echo strtoupper($total['label']); ?>:</strong> <?php echo $total['value']; ?></td>
						</tr>
					<?php } ?>
				<?php endforeach; ?>
				
			</table>
		</td>
	</tr>
	
	<?php
		if($order->get_customer_note() != '')
		{
	?>
			<tr>
				<td>
					<table>
						
						<tr>
							<td><strong>NOTA:</strong> <?php echo $order->get_customer_note(); ?></td>
						</tr>
						
					</table>
				</td>
			</tr>
	<?php
		}
	?>
	
	<tr><td height="10"></td></tr>
	
</table>

<hr/>

<table class="delivery-details">
	<tr>
		<td height="10"></td>
	</tr>
	
	<tr>
		<td>
			<table>
				<tr>
					<td>Recortar para envíos</td>
				</tr>
			</table>
		</td>
	</tr>
	
	<tr>
		<td height="10"></td>
	</tr>
	
	<tr>
		<td>
			<table>
				<tr>
					<td><strong>Comprobante de envío</strong></td>
				</tr>
			</table>
		</td>
	</tr>
	
	<tr>
		<td height="10"></td>
	</tr>
	
	<tr>
		<td>
			<table>
				<tr>
					<td><strong>Factura</strong></td>
					<td># <?php $this->order_number(); ?></td>
				</tr>
				<tr>
					<td><strong>Nombre</strong></td>
					<td><?php echo strtoupper(get_post_meta($order_id, '_billing_first_name', true) . ' ' . get_post_meta($order_id, '_billing_last_name', true)); ?></td>
				</tr>
				<tr>
					<td><strong>Cédula</strong></td>
					<td><?php echo strtoupper(get_post_meta($order_id, '_billing_cedula', true)); ?></td>
				</tr>
				<tr>
					<td><strong>Teléfono</strong></td>
					<td><?php echo strtoupper(get_post_meta($order_id, '_billing_phone', true)); ?></td>
				</tr>
				<tr>
					<td><strong>Emp Envio</strong></td>
					<td><?php echo strtoupper($shipping_method_title); ?></td>
				</tr>
				<tr>
					<td><strong>Ciudad</strong></td>
					<td><?php echo strtoupper(get_post_meta($order_id, '_billing_city', true)); ?></td>
				</tr>
				<tr>
					<td><strong>Estado</strong></td>
					<td><?php echo strtoupper($state_string); ?></td>
				</tr>
				<tr>
					<td><strong>Barrio</strong></td>
					<td><?php echo strtoupper(get_post_meta($order_id, '_billing_barrio', true)); ?></td>
				</tr>
				<tr>
					<td><strong>Dirección</strong></td>
					<td><?php echo strtoupper(get_post_meta($order_id, '_billing_address_1', true) . '. ' . get_post_meta($order_id, '_billing_address_2', true))  ; ?></td>
				</tr>
				<tr>
					<td><strong>Pais</strong></td>
					<td><?php echo strtoupper($country_string); ?></td> 
				</tr>
			</table>
		</td>
	</tr>
	
	<tr>
		<td height="10"></td>
	</tr>
	
	<tr>
		<td>
			<table>
				<tr>
					<td><strong>INVERSIONES JL</strong></td>
				</tr>
				<tr>
					<td><strong>Gracias por su compra</strong></td>
				</tr>
			</table>
		</td>
	</tr>
</table>

