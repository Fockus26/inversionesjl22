<?php
/**
 * Shipping report (WooCommerce › Informe de Envíos): a form picks country, courier, dates and
 * shift; admin-post.php?action=shipping_report finds the completed orders and streams an 80 mm
 * PDF. Split in one file per WPCodeBox snippet, each moved as is.
 *
 * This file: the report's HTML (with assets/css/pdf-shipping-report.css). From Shipping
 * Report › PDF UI (snippet 51). Only change: its own ob_start() before the HTML; it returned
 * ob_get_clean() of whatever buffer PHP had open (output_buffering), an empty PDF without one.
 * The CSS was the PDF plugin's custom/shipping-report.css: the same file, now in this plugin.
 */

defined( 'ABSPATH' ) || exit;

function pdfUIShippingReport($args) {
    extract($args);

    $date = formatDateRange($startDate, $endDate);
    $totalOrders = count($orders);

    $cssFile = INVERSIONESJL22_SITE_DIR . 'assets/css/pdf-shipping-report.css';
    $css = file_exists($cssFile) ? file_get_contents($cssFile) : '';
    // The CSS is written for 80 mm; its @page takes the configured paper (WooCommerce › Documentos).
    $css = str_replace('size: 80mm auto', 'size: ' . inversionesjl22_site_document_paper()['mm'] . 'mm auto', $css);

    ob_start();
    ?>
      <style>
          <?php echo $css; ?>
      </style>

      <div id="pdf-shipping-report">
        <!-- Client -->
        <div>
          <h1>Informe de Envíos</h1>

          <table class="client-info">
            <tr>
              <td>País</td>
              <td><?php echo esc_html($country); ?></td>
            </tr>
            <tr>
              <td>Método de envío</td>
              <td><?php echo esc_html($company); ?></td>
            </tr>
            <tr>
              <td>Fechas</td>
              <td><?php echo esc_html($date); ?></td>
            </tr>
            <tr>
              <td>Pedidos Totales</td>
              <td><?php echo esc_html($totalOrders);?></td>
            </tr>
          </table>
        </div>

        <!-- Shipping -->
        <div>
          <h2>Notas de Envío</h2>
          <table class="shipping-info">
            <thead>
              <tr>
                <th>Pedido</th>
                <th>Hora</th>
                <th>Monto Envío</th>
                <th>Nota</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($orders as $order):
                  $order = wc_get_order($order->ID);

                  $status = get_post_meta($order->get_id(), "_payment-status", true);
                  $amount = get_post_meta($order->get_id(), "_amount", true);
                  $currency = get_post_meta($order->get_id(), "_currency", true);

                  $orderId = $order->get_order_number();
                  $orderDate = $order->get_date_completed();
                  $orderTime = $orderDate
                      ? strtolower($orderDate->date('g:ia'))
                      : '--';


                  // Determinar monto y moneda final a mostrar
                  if ($status === "pending" && $amount > 0) {
                      // Usar el monto definido manualmente
                      $currency =
                          $currency === "dolares"
                              ? "USD"
                              : ($currency === "pesos"
                                  ? "COP"
                                  : strtoupper($currency));
                      $amount = number_format(floatval($amount), 2, ".", "");
                  } else {
                      // Usar el costo de envío del pedido
                      $amount = $order->get_shipping_total() + $order->get_shipping_tax();
                      $currency = $order->get_currency();
                      $amount = number_format(floatval($amount), 2, ".", "");
                  }

                  $total = sprintf("%s %s", esc_html($currency), esc_html($amount));
                  ?>
              <tr>
                <td><?php echo esc_html($orderId); ?></td>
                <td><?php echo esc_html($orderTime); ?></td>
                <td><?php echo $total; ?></td>
                <td></td>
              </tr>
              <?php
              endforeach; ?>

            </tbody>
          </table>
        </div>

        <div class="footer">
          <p class="brand">INVERSIONES JL</p>
          <p>Gracias por su compra</p>
        </div>
      </div>
    <?php
    return ob_get_clean();
}
