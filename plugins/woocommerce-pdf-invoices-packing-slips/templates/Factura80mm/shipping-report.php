<?php if ( ! defined( 'ABSPATH' ) ) exit; // Evitar acceso directo ?>

<!-- Encabezado del reporte con título centrado -->
<div class="document-header text-center">
  <div class="document-title bold">Informe de Envíos</div>
</div>

<!-- Tabla con información básica del cliente y rango de fechas -->
<table class="client-info-table">
  <tr>
    <td class="bold">País:</td>
    <td><?php echo esc_html( $pais ); ?></td>
  </tr>
  <tr>
    <td class="bold">Método de envío:</td>
    <td><?php echo esc_html( $empresa_nombre ); ?></td>
  </tr>
  <tr>
    <td class="bold">Rango de fechas:</td>
    <td><?php echo esc_html( $fecha_inicio . ' a ' . $fecha_fin ); ?></td>
  </tr>
  <tr>
    <td class="bold">Total pedidos:</td>
    <td><?php echo count( $pedidos ); // Número total de pedidos ?></td>
  </tr>
</table>

<!-- Sección para mostrar notas de envío asociadas a los pedidos -->
<div class="delivery-section">
  <div class="delivery-header text-center">Notas de Envío</div>
  <div class="table-container">
    <table class="items-table">
      <thead>
        <tr>
          <th class="product-price text-center" style="width: 20%;">Pedido</th>
          <th class="product-name text-center" style="width: 80%;">Nota</th>
        </tr>
      </thead>
      <tbody>
        <!-- Recorrer cada pedido para mostrar su número y espacio para nota -->
        <?php foreach ( $pedidos as $pedido ) :
          $order = wc_get_order( $pedido->ID );
        ?>
        <tr class="shipping-note-row">
          <td class="shipping-order"><?php echo $order->get_order_number(); ?></td>
          <td class="shipping-note"></td> <!-- Campo para nota editable o impresión -->
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Pie de página del documento -->
<div class="delivery-footer text-center">
  <div class="bold">INVERSIONES JL</div>
  <div class="bold">Gracias por su compra</div>
</div>
