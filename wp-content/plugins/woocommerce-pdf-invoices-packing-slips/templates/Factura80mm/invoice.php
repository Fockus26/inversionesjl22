<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<?php do_action( 'wpo_wcpdf_before_document', $this->get_type(), $this->order ); ?>

<?php
$user_id = $order->get_user_id();

$customer_status = $user_id
    ? get_user_meta($user_id, '_jl_customer_status', true)
    : '';

$customer_status = $customer_status
    ?: $order->get_meta('_jl_customer_status', true);

$customer_statuses = [
    'standard'   => 'ESTÁNDAR',
    'rookie'     => 'NUEVO',
    'premium'    => 'PREMIUM',
    'influencer' => 'INFLUENCER',
    'selecto'    => 'SELECTO',
    'priority'   => 'PRIORIDAD',
    'wholesaler' => 'MAYORISTA',
    'academy'    => 'ACADEMIA',
];

$customer_status_label = isset($customer_statuses[$customer_status])
    ? $customer_statuses[$customer_status]
    : 'ESTÁNDAR';

$order_id = $this->order->get_id();

$qr_text  = "https://inversionesjl22.com?action=qr_scan&order_id={$order_id}";

// generateQrCode() vive en el snippet "scan qr" de WPCodeBox; si el snippet está desactivado, el PDF sale sin QR en vez de fallar
$qr_image = function_exists( 'generateQrCode' ) ? generateQrCode( $qr_text ) : false;

$countries = new WC_Countries();
$country_code = $order->get_billing_country();
$state_code = $order->get_billing_state();
$country_name = isset($countries->get_countries()[$country_code]) ? $countries->get_countries()[$country_code] : $country_code;
$states = $countries->get_states($country_code);
$state_name = (is_array($states) && isset($states[$state_code])) ? $states[$state_code] : $state_code;
$postcode = $order->get_billing_postcode();
$postcode_value = !empty($postcode) ? $postcode : 'N/A';

$billing_data = [
    'name'      => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
    'email'     => $order->get_billing_email(),
    'cedula'    => $order->get_meta('_billing_cedula'),
    'phone'     => $order->get_billing_phone(),
    'city'      => $order->get_billing_city(),
    'barrio'    => $order->get_meta('_billing_barrio'),
    'address1'  => $order->get_billing_address_1(),
    'address2'  => $order->get_billing_address_2(),
    'country'   => $country_name,
    'state'     => $state_name,
    'postcode'  => $postcode_value
];

$fields = [
    'email' => [
        'label' => 'CORREO',
        'value' => $billing_data['email'],
        'required' => false
    ],
    'client_name' => [
        'label' => 'NOMBRE',
        'value' => $billing_data['name'],
        'required' => true
    ],
    'cedula' => [
        'label' => 'CEDULA',
        'value' => $billing_data['cedula'],
        'required' => false
    ],
    'phone' => [
        'label' => 'TELEFONO',
        'value' => $billing_data['phone'],
        'required' => true
    ],
    'city' => [
        'label' => 'CIUDAD',
        'value' => $billing_data['city'],
        'required' => true
    ],
    'state' => [
        'label' => 'ESTADO',
        'value' => $billing_data['state'],
        'required' => true
    ],
    'barrio' => [
        'label' => 'BARRIO',
        'value' => $billing_data['barrio'],
        'required' => false
    ],
    'address' => [
        'label' => 'DIRECCION',
        'value' => trim(implode('. ', array_filter([
            $billing_data['address1'],
            $billing_data['address2']
        ]))),
        'required' => true
    ],
    'country' => [
        'label' => 'PAIS',
        'value' => $billing_data['country'],
        'required' => true
    ],
    'postcode' => [ 
        'label' => 'CODIGO POSTAL',
        'value' => $billing_data['postcode'],
        'required' => true 
    ]
];

$shipping_methods = [
    'CONTRA ENTREGA EFECTIVO',
    'DOMICILIO CUCUTA',
    'DOMICILIO SAN CRISTOBAL',
    'ENTREGAS TEALCA'
];

$status = get_post_meta($order_id, '_payment-status', true);
$amount = get_post_meta($order_id, '_amount', true);
$currency = get_post_meta($order_id, '_currency', true);

$shipping_items = $order->get_items('shipping');
$show_pago = false;
$current_method = '';
if (!empty($shipping_items)) {
    $first_item = reset($shipping_items);
    $current_method = $first_item->get_method_title();
    if (in_array($current_method, $shipping_methods, true)) {
        $show_pago = true;
    }
}

$shipping_company = !empty($current_method) ? strtoupper($current_method) : '';

$shipping_method = strtoupper(trim($order->get_shipping_method()));

$invoice = method_exists($this, 'get_invoice')
    ? $this->get_invoice()
    : null;

$date = $invoice
    ? $invoice->get_date()
    : $order->get_date_created();

$invoice_date_es = '';

if ($date) {
    $months = [
        1 => 'enero',
        2 => 'febrero',
        3 => 'marzo',
        4 => 'abril',
        5 => 'mayo',
        6 => 'junio',
        7 => 'julio',
        8 => 'agosto',
        9 => 'septiembre',
        10 => 'octubre',
        11 => 'noviembre',
        12 => 'diciembre'
    ];

    $invoice_date_es =
        $date->date_i18n('d') .
        ' de ' .
        $months[(int)$date->date_i18n('n')] .
        ' de ' .
        $date->date_i18n('Y');
}

$order_items = $this->get_order_items();
$customer_note = $order->get_customer_note();

$order_total = null;

foreach ($this->get_woocommerce_totals() as $total) {
    if ($total['label'] === 'Total') {
        $order_total = $total;
        break;
    }
}

$copies = ['COPIA 1', 'COPIA 2'];
?>

<?php foreach ($copies as $index => $copy_label) : ?>
<div
    class="customer-info-section"
    style="<?php echo $index > 0 ? 'page-break-before: always;' : ''; ?>"
>

<?php if (current_user_can('manage_woocommerce') && $qr_image) : ?>

    <div class="heading">
        <?php echo esc_html($copy_label); ?>
    </div>

    <div class="qr-code" style="text-align:center;margin-bottom:5mm;">
        <img
            src="<?php echo esc_attr($qr_image); ?>"
            alt="QR Code"
            style="width:20mm;height:20mm;"
        />
    </div>

    <div class="heading" style="margin-top:3mm;">
        <?php echo esc_html($customer_status_label); ?>
    </div>

<?php endif; ?>

<div class="document-header">
    <div class="document-title bold text-center mb-1">
        COMPROBANTE DE VENTA
    </div>

    <div class="text-center">
        #<?php $this->order_number(); ?>
    </div>

    <div class="text-center">
        <?php echo esc_html($invoice_date_es); ?>
    </div>
</div>

<table class="client-info-table">

    <?php foreach ($fields as $field) :

        if (empty($field['value']) && !$field['required']) {
            continue;
        }

        $value = !empty($field['value'])
            ? $field['value']
            : 'NO ESPECIFICADO';

    ?>

    <tr>
        <td class="bold">
            <?php echo esc_html($field['label']); ?>
        </td>

        <td>
            <?php echo esc_html(strtoupper(trim($value))); ?>
        </td>
    </tr>

    <?php endforeach; ?>

</table>

<?php if (!empty($customer_note)) : ?>
    <div
        class="customer-note"
        style="margin:5mm 0;font-size:8pt;"
    >
        <div class="bold">
            NOTA DEL CLIENTE
        </div>

        <div>
            <?php echo nl2br(esc_html($customer_note)); ?>
        </div>
    </div>
<?php endif; ?>
</div>

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

            <?php foreach ($order_items as $item_id => $item) : ?>

                <tr class="product-separator">
                    <td colspan="3" style="border-top:1px dashed #26292c;height:3mm;"></td>
                </tr>

                <tr class="product-row">

                    <td class="product-name">

                        <div class="bold product-title">

                            <?php echo strtoupper(esc_html($item['name'])); ?>

                            <?php
                            $product = $item['item']->get_product();

                            if ($product && $sku = $product->get_sku()) {
                                echo '<div class="product-sku">SKU: ' .
                                    esc_html($sku) .
                                    '</div>';
                            }
                            ?>

                        </div>

                        <?php if (!empty($item['variation_id'])) : ?>

                            <div class="variation-attributes">

                                <?php
                                foreach ($item['item']->get_meta_data() as $meta) {

                                    $data = $meta->get_data();

                                    if ($data['key'] === '_reduced_stock') {
                                        continue;
                                    }

                                    $value = trim($data['value']);

                                    if (!empty($value)) {

                                        $key = str_replace(
                                            ['attribute_pa_', 'pa_'],
                                            '',
                                            $data['key']
                                        );

                                        $key = ucwords(
                                            str_replace('_', ' ', $key)
                                        );

                                        echo '<div class="attribute">'
                                            . esc_html($key)
                                            . ': '
                                            . esc_html($value)
                                            . '</div>';
                                    }
                                }
                                ?>

                            </div>

                        <?php endif; ?>

                    </td>

                    <td class="product-qty">
                        <?php echo $item['quantity']; ?>
                    </td>

                    <td class="product-price">
                        <?php echo $item['single_price']; ?>
                    </td>

                </tr>

            <?php endforeach; ?>

            <tr class="last-separator">
                <td
                    colspan="3"
                    style="border-bottom:1px solid #000;height:1mm;"
                ></td>
            </tr>

        </tbody>

    </table>

</div>

<div class="order-total">
    <?php if ($order_total) : ?>
        <div class="total-row">
            <span class="total-label">
                <?php echo strtoupper($order_total['label']); ?>:
            </span>

            <span class="total-value">
                <?php echo $order_total['value']; ?>
            </span>
        </div>
    <?php endif; ?>
</div>

</div>

<?php endforeach; ?>

<div class="shipping-section">
  <div class="delivery-spacer"></div>
  <div class="delivery-cut-message text-center">Recortar para envíos</div>
  <div class="delivery-spacer"></div>

  <?php
    if ($show_pago) {
        $pago_texto = '';
        if ($status === 'paid') {
            $pago_texto = 'PAGADO';
        } elseif ($status === 'pending' && $amount > 0) {
            $pago_texto = 'POR COBRAR: ' . number_format($amount, 2) . ' ' . strtoupper($currency);
        }

        if (!empty($pago_texto)) {
            ?>
            <div class="heading">
              <?php echo esc_html($pago_texto); ?>
            </div>
            <?php
        }

        if (!empty($shipping_company)) {
            ?>
            <div class="heading" style="margin-bottom: 16px;">
              <?php echo esc_html($shipping_company); ?>
            </div>
            <?php
        }
    } else {
        if (!empty($shipping_method)) {
            ?>
            <div class="heading" style="margin-bottom: 16px;">
              <?php echo esc_html($shipping_method); ?>
            </div>
            <?php
        }
    }
  ?>

  <?php if ( current_user_can('manage_woocommerce') && $qr_image ) : ?>
    <div class="qr-code" style="text-align: center; margin-bottom: 5mm;">
      <img src="<?php echo esc_attr( $qr_image ); ?>" alt="QR Code" style="width: 20mm; height: 20mm;" />
    </div>
    <div class="heading" style="margin-top: 3mm;">
      <?php echo esc_html($customer_status_label); ?>
    </div>
  <?php endif; ?>

  <div class="delivery-spacer"></div>

  <div class="delivery-header bold text-center">Comprobante de envio</div>
  <div class="delivery-spacer"></div>

  <table class="delivery-info-table">
    <tr>
      <td class="bold">Factura</td>
      <td>#<?php $this->order_number(); ?></td>
    </tr>
    <?php if (!empty($billing_data['email'])) : ?>
    <tr>
      <td class="bold">Correo</td>
      <td><?php echo esc_html($billing_data['email']); ?></td>
    </tr>
    <?php endif; ?>
    <tr><td class="bold">Nombre</td><td><?php echo esc_html(strtoupper($billing_data['name'])); ?></td></tr>
    <tr><td class="bold">Cedula</td><td><?php echo esc_html(strtoupper($billing_data['cedula'])); ?></td></tr>
    <tr><td class="bold">Teléfono</td><td><?php echo esc_html(strtoupper($billing_data['phone'])); ?></td></tr>
    <tr><td class="bold">Emp Envio</td><td><?php echo esc_html($shipping_method); ?></td></tr>
    <tr><td class="bold">Ciudad</td><td><?php echo esc_html(strtoupper($billing_data['city'])); ?></td></tr>
    <tr><td class="bold">Estado</td><td><?php echo esc_html(strtoupper($state_name)); ?></td></tr>
    <tr><td class="bold">Barrio</td><td><?php echo esc_html(strtoupper($billing_data['barrio'])); ?></td></tr>
    <tr><td class="bold">Direccion</td><td><?php echo esc_html(strtoupper($fields['address']['value'])); ?></td></tr>
    <tr><td class="bold">Pais</td><td><?php echo esc_html(strtoupper($country_name)); ?></td></tr>
    <tr><td class="bold">Código Postal</td><td><?php echo esc_html(strtoupper($billing_data['postcode'])); ?></td></tr>
  </table>

  <div class="delivery-spacer"></div>

  <div class="delivery-footer text-center">
    <div class="bold">INVERSIONES JL</div>
    <div class="bold">Gracias por su compra</div>
  </div>
</div>

<?php do_action( 'wpo_wcpdf_after_document', $this->get_type(), $this->order ); ?>
