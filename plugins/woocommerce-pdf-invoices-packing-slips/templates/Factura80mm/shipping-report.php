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
          <th class="text-center" style="width: 20%;">Pedido</th>
          <th class="text-center" style="width: 40%;">Monto Envío</th>
          <th class="text-center" style="width: 40%;">Nota</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $pedidos as $pedido ) :
          $order = wc_get_order( $pedido->ID );

          // Datos de pago personalizados (si existen)
          $jl_status   = get_post_meta( $order->get_id(), '_jl_pago_status', true );
          $jl_amount   = get_post_meta( $order->get_id(), '_jl_pago_amount', true );
          $jl_currency = get_post_meta( $order->get_id(), '_jl_pago_currency', true );

          // Determinar monto y moneda final a mostrar
          if ( $jl_status === 'por_cobrar' && $jl_amount > 0 ) {
              // Usar el monto definido manualmente
              $display_currency = $jl_currency === 'dolares' ? 'USD' : ($jl_currency === 'pesos' ? 'COP' : strtoupper($jl_currency));
              $formatted_cost = number_format(floatval($jl_amount), 2, '.', '');
              $shipping_display = sprintf('%s %s', esc_html($display_currency), esc_html($formatted_cost));
          } else {
              // Usar el costo de envío del pedido
              $shipping_total = $order->get_shipping_total() + $order->get_shipping_tax();
              $currency = $order->get_currency();
              $formatted_cost = number_format(floatval($shipping_total), 2, '.', '');
              $shipping_display = sprintf('%s %s', esc_html($currency), esc_html($formatted_cost));
          }
        ?>
        <tr class="shipping-note-row">
          <td class="shipping-order text-center"><?php echo $order->get_order_number(); ?></td>
          <td class="shipping-cost text-center"><?php echo $shipping_display; ?></td>
          <td class="shipping-note"></td>
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
