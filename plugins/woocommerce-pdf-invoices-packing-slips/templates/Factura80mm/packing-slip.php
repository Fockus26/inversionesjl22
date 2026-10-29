<?php if ( ! defined( 'ABSPATH' ) ) exit; // Evitar acceso directo ?>

<?php
// Variables usadas abajo (antes no estaban definidas y generaban avisos en PHP 8)
$shipping_method_title = $order->get_shipping_method();
$state_list            = WC()->countries->get_states( $order->get_billing_country() );
$state_string          = $state_list[ $order->get_billing_state() ] ?? $order->get_billing_state();
?>

<!-- Acción antes de generar el documento -->
<?php do_action( 'wpo_wcpdf_before_document', $this->get_type(), $this->order ); ?>

<!-- Encabezado del documento con título y número de pedido -->
<div class="document-header">
  <div class="document-title bold">COMPROBANTE DE ENVÍO</div>
  <div class="text-center">#<?php $this->order_number(); ?></div>
</div>

<!-- Tabla con información del envío -->
<table class="shipping-info-table">
  <tr>
    <td class="bold">EMPRESA:</td>
    <td><?php echo strtoupper($shipping_method_title); // Método de envío en mayúsculas ?></td>
  </tr>
  <tr>
    <td class="bold">DIRECCIÓN:</td>
    <td>
      <?php 
        // Dirección de facturación: calle, ciudad, estado en mayúsculas
        echo strtoupper( 
          $order->get_billing_address_1() . ', ' . 
          $order->get_billing_city() . ', ' . 
          $state_string 
        ); 
      ?>
    </td>
  </tr>
  <!-- Aquí se pueden agregar más campos personalizados si se requiere -->
</table>

<!-- Tabla compacta con los productos y cantidades -->
<table class="items-table compact">
  <thead>
    <tr>
      <th class="product-name">PRODUCTO</th>
      <th class="product-qty">CANT</th>
    </tr>
  </thead>
  <tbody>
    <!-- Recorrer los ítems del pedido -->
    <?php foreach( $this->get_order_items() as $item_id => $item ) : ?>
    <tr>
      <td class="product-name"><?php echo $item['name']; ?></td>
      <td class="product-qty text-center"><?php echo $item['quantity']; ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<!-- Acción después de generar el documento -->
<?php do_action( 'wpo_wcpdf_after_document', $this->get_type(), $this->order ); ?>
