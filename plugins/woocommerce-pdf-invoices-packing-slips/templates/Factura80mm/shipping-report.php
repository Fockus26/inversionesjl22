<?php if ( ! defined( 'ABSPATH' ) ) exit; // Evitar acceso directo ?>
<!-- Encabezado del reporte con título centrado -->
<div class="document-header text-center">
  <div class="document-title bold">Informe de Donde no se debio enviar</div>
</div>

<!-- Tabla con información básica del cliente y rango de fechas -->
<table class="client-info-table">
  <tr>
    <td class="bold">País:</td>
    <td><?php echo esc_html( $country ); ?></td>
  </tr>
  <tr>
    <td class="bold">Método de envío:</td>
    <td><?php echo esc_html( $company ); ?></td>
  </tr>
  <tr>
    <td class="bold">Rango de fechas:</td>
    <td><?php echo esc_html( $startDate . ' a ' . $endDate ); ?></td>
  </tr>
  <tr>
    <td class="bold">Total pedidos:</td>
    <td><?php echo count( $orders ); // Número total de pedidos ?></td>
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
        <?php foreach ( $orders as $order ) :
          $order = wc_get_order( $order->ID );

          // Datos de pago personalizados (si existen)
          $status   = get_post_meta( $order->get_id(), '_jl_pago_status', true );
          $amount   = get_post_meta( $order->get_id(), '_jl_pago_amount', true );
          $currency = get_post_meta( $order->get_id(), '_jl_pago_currency', true );

          // Determinar monto y moneda final a mostrar
          if ( $status === 'por_cobrar' && $amount > 0 ) {
              // Usar el monto definido manualmente
              $currency = $currency === 'dolares' ? 'USD' : ($currency === 'pesos' ? 'COP' : strtoupper($currency));
              $amount = number_format(floatval($amount), 2, '.', '');
              $total = sprintf('%s %s', esc_html($currency), esc_html($amount));
          } else {
              // Usar el costo de envío del pedido
              $cost = $order->get_shipping_total() + $order->get_shipping_tax();
              $currency = $order->get_currency();
              $cost = number_format(floatval($cost), 2, '.', '');
              $total = sprintf('%s %s', esc_html($currency), esc_html($cost));
          }
        ?>
        <tr class="shipping-note-row">
          <td class="shipping-order text-center"><?php echo $order->get_order_number(); ?></td>
          <td class="shipping-cost text-center"><?php echo $total; ?></td>
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
