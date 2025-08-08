<?php if ( ! defined( 'ABSPATH' ) ) exit; // Evitar acceso directo al archivo ?>

<?php do_action( 'wpo_wcpdf_before_document', $this->type, $this->order ); // Hook antes de renderizar el documento ?>

<?php 
// Obtener ID del pedido de forma segura, compatible con diferentes versiones
$order_id = ( method_exists( $this->order, 'get_id' ) ) ? $this->order->get_id() : $order->get_id();

// Crear nonce para acciones seguras (no usado aquí directamente, pero generado)
$nonce = wp_create_nonce( 'update_order_status' );

// Texto para generar código QR que apunta a una URL con acción y ID del pedido
$qr_text  = "https://inversionesjl22.com?action=qr_scan&order_id={$order_id}";

// Generar imagen QR en base64 para incrustar en el PDF
$qr_image = generate_base64_qr_code( $qr_text );
?>

<?php if ( current_user_can('manage_woocommerce') && $qr_image ) : ?>
<!-- Mostrar código QR solo si el usuario tiene permiso y la imagen existe -->
<div class="qr-code" style="text-align: center; margin-bottom: 5mm;">
  <img src="<?php echo esc_attr( $qr_image ); ?>" alt="QR Code" style="width: 20mm; height: 20mm;" />
</div>
<?php endif; ?>

<!-- SECCIÓN 1: Datos del cliente -->
<div class="customer-info-section">
  <div class="document-header">
    <div class="document-title bold text-center mb-1">COMPROBANTE DE VENTA</div> <!-- Título centrado -->
    <div class="text-center">#<?php $this->order_number(); ?></div>               <!-- Número de factura -->
    <div class="text-center"><?php $this->invoice_date(); ?></div>                <!-- Fecha de factura -->
  </div>

  <?php
  // Obtener nombres legibles de país y estado usando WC_Countries
  $countries = new WC_Countries();
  $country_code = $order->get_billing_country();
  $state_code = $order->get_billing_state();
  $country_name = isset($countries->get_countries()[$country_code]) ? $countries->get_countries()[$country_code] : $country_code;
  $states = $countries->get_states($country_code);
  $state_name = isset($states[$state_code]) ? $states[$state_code] : $state_code;

  // Agrupar datos de facturación para usarlos más fácil
  $billing_data = [
      'name'      => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
      'cedula'    => $order->get_meta('_billing_cedula'),
      'phone'     => $order->get_billing_phone(),
      'city'      => $order->get_billing_city(),
      'barrio'    => $order->get_meta('_billing_barrio'),
      'address1'  => $order->get_billing_address_1(),
      'address2'  => $order->get_billing_address_2(),
      'country'   => $country_name,
      'state'     => $state_name
  ];

  // Campos que se mostrarán con sus etiquetas y si son obligatorios o no
  $fields = [
      'name'    => ['label' => 'CLIENTE',   'value' => $billing_data['name'], 'required' => true],
      'cedula'  => ['label' => 'CEDULA',    'value' => $billing_data['cedula'], 'required' => false],
      'phone'   => ['label' => 'TELEFONO',  'value' => $billing_data['phone'], 'required' => true],
      'city'    => ['label' => 'CIUDAD',    'value' => $billing_data['city'], 'required' => true],
      'state'   => ['label' => 'ESTADO',    'value' => $billing_data['state'], 'required' => true],
      'barrio'  => ['label' => 'BARRIO',    'value' => $billing_data['barrio'], 'required' => false],
      'address' => [
          'label' => 'DIRECCION', 
          'value' => trim(implode('. ', array_filter([
              $billing_data['address1'], 
              $billing_data['address2']
          ]))),
          'required' => true
      ],
      'country' => ['label' => 'PAIS', 'value' => $billing_data['country'], 'required' => true]
  ];
  ?>

  <!-- Tabla con datos del cliente -->
  <table class="client-info-table">
    <?php foreach ($fields as $field): 
      // Omitir campos opcionales vacíos
      if (empty($field['value']) && !$field['required']) continue;

      // Si el valor está vacío y es obligatorio, mostrar "NO ESPECIFICADO"
      $value = !empty($field['value']) ? $field['value'] : 'NO ESPECIFICADO';
    ?>
    <tr>
      <td class="bold"><?php echo esc_html($field['label']); ?></td> <!-- Etiqueta -->
      <td><?php echo esc_html(strtoupper(trim($value))); ?></td>    <!-- Valor en mayúsculas -->
    </tr>
    <?php endforeach; ?>
  </table>

  <?php 
  // Mostrar nota del cliente si existe
  $customer_note = $order->get_customer_note();
  if ( !empty($customer_note) ) : 
  ?>
    <div class="customer-note" style="margin: 5mm 0; font-size: 8pt;">
      <div class="bold">NOTA DEL CLIENTE</div>
      <div><?php echo nl2br( esc_html( $customer_note ) ); ?></div> <!-- Nota con saltos de línea -->
    </div>
  <?php endif; ?>
</div>

<!-- SECCIÓN 2: Productos -->
<div class="product-list-section">
  <div class="customer-product-gap"></div>

  <div class="table-container">
    <table class="items-table">
      <thead>
        <tr>
          <th class="product-name">PRODUCTO</th>
          <th class="product-qty">CANT</th>
          <th class="product-price">PU</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach( $this->get_order_items() as $item_id => $item ) : ?>
        <!-- Separador entre productos -->
        <tr class="product-separator">
          <td colspan="3" style="border-top: 1px dashed #26292c; height: 3mm;"></td>
        </tr>

        <tr class="product-row">
          <td class="product-name">
            <div class="bold product-title">
              <?php echo strtoupper(esc_html($item['name'])); // Nombre del producto en mayúsculas ?>
              <?php 
              // Mostrar SKU si existe
              $product = $item['item']->get_product();
              if ($product && $sku = $product->get_sku()) {
                  echo '<div class="product-sku">SKU: ' . esc_html($sku) . '</div>';
              }
              ?>
            </div>

            <?php if(!empty($item['variation_id'])) : ?>
              <!-- Mostrar atributos de variación -->
              <div class="variation-attributes">
                <?php 
                foreach($item['item']->get_meta_data() as $meta) {
                  $data = $meta->get_data();

                  // Ignorar meta _reduced_stock
                  if ($data['key'] === '_reduced_stock') continue;

                  $value = trim($data['value']);
                  if (!empty($value)) {
                      // Limpiar nombre del atributo
                      $key = str_replace(['attribute_pa_', 'pa_'], '', $data['key']);
                      $key = ucwords(str_replace('_', ' ', $key));
                      echo '<div class="attribute">' . esc_html($key) . ': ' . esc_html($value) . '</div>';
                  }
                }
                ?>
              </div>
            <?php endif; ?>
          </td>
          <td class="product-qty"><?php echo $item['quantity']; // Cantidad ?></td>
          <td class="product-price"><?php echo $item['single_price']; // Precio unitario ?></td>
        </tr>
        <?php endforeach; ?>

        <!-- Separador final debajo de la lista de productos -->
        <tr class="last-separator">
          <td colspan="3" style="border-bottom: 1px solid #000; height: 1mm;"></td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Mostrar total de la orden (solo el total final) -->
  <div class="order-total">
    <?php foreach( $this->get_woocommerce_totals() as $key => $total ) : ?>
      <?php if( $total['label'] === 'Total' ) : ?>
      <div class="total-row">
        <span class="total-label"><?php echo strtoupper($total['label']); ?>:</span>
        <span class="total-value"><?php echo $total['value']; ?></span>
      </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>

<!-- SECCIÓN 3: Comprobante de Envío -->
<div class="shipping-section">
  <div class="delivery-spacer"></div>
  <div class="delivery-cut-message text-center">Recortar para envíos</div> <!-- Indicador para recorte físico -->
  <div class="delivery-spacer"></div>

  <?php if ( current_user_can('manage_woocommerce') && $qr_image ) : ?>
    <!-- Mostrar código QR en el comprobante de envío -->
    <div class="qr-code" style="text-align: center; margin-bottom: 5mm;">
      <img src="<?php echo esc_attr( $qr_image ); ?>" alt="QR Code" style="width: 20mm; height: 20mm;" />
    </div>
  <?php endif; ?>

  <div class="delivery-spacer"></div>

  <!-- Título del comprobante de envío -->
  <div class="delivery-header bold text-center">Comprobante de envio</div>
  <div class="delivery-spacer"></div>

  <!-- Tabla con información de envío y facturación -->
  <table class="delivery-info-table">
    <tr><td class="bold">Factura</td><td>#<?php $this->order_number(); ?></td></tr>
    <tr><td class="bold">Nombre</td><td><?php echo esc_html(strtoupper($billing_data['name'])); ?></td></tr>
    <tr><td class="bold">Cedula</td><td><?php echo esc_html(strtoupper($billing_data['cedula'])); ?></td></tr>
    <tr><td class="bold">Teléfono</td><td><?php echo esc_html(strtoupper($billing_data['phone'])); ?></td></tr>
    <tr><td class="bold">Emp Envio</td><td><?php echo esc_html(strtoupper($order->get_shipping_method())); ?></td></tr>
    <tr><td class="bold">Ciudad</td><td><?php echo esc_html(strtoupper($billing_data['city'])); ?></td></tr>
    <tr><td class="bold">Estado</td><td><?php echo esc_html(strtoupper($state_name)); ?></td></tr>
    <tr><td class="bold">Barrio</td><td><?php echo esc_html(strtoupper($billing_data['barrio'])); ?></td></tr>
    <tr><td class="bold">Direccion</td><td><?php echo esc_html(strtoupper($fields['address']['value'])); ?></td></tr>
    <tr><td class="bold">Pais</td><td><?php echo esc_html(strtoupper($country_name)); ?></td></tr>
  </table>

  <div class="delivery-spacer"></div>

  <!-- Pie de página con nombre y agradecimiento -->
  <div class="delivery-footer text-center">
    <div class="bold">INVERSIONES JL</div>
    <div class="bold">Gracias por su compra</div>
  </div>
</div>

<?php do_action( 'wpo_wcpdf_after_document', $this->type, $this->order ); // Hook después de renderizar el documento ?>
