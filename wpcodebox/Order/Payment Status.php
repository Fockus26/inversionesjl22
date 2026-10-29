<?php
add_action('woocommerce_admin_order_data_after_shipping_address', 'paymentStatusUI');
function paymentStatusUI($order){
    $shippingMethods = getPickUpShippingMethods();

    $methods = $order->get_shipping_methods();
    $show = false;
    foreach ($methods as $method) {
        if (in_array($method->get_method_title(), $shippingMethods)) {
            $show = true;
            break;
        }
    }
    if (!$show) return;

    $status = get_post_meta($order->get_id(), '_payment-status', true);
    $amount = get_post_meta($order->get_id(), '_amount', true);
    $currency = get_post_meta($order->get_id(), '_currency', true);

    ?>
    <div class="my-form">
        <fieldset>
            <div>
                <label for="payment-status">Estado de pago</label>
                <select id="payment-status" name="payment-status">
                    <option value="">--- Seleccione ---</option>
                    <option value="paid" <?php selected($status, 'paid'); ?>>Pagado</option>
                    <option value="pending" <?php selected($status, 'pending'); ?>>Por Cobrar</option>
                </select>
            </div>
            <div id="payment-info" style="<?php echo ($status == 'pending') ? '' : 'display:none;'; ?>">
                <label for="payment-status">Moneda</label>
                <select id="currency" name="currency">
                    <option value="pesos" <?php selected($currency, 'pesos'); ?>>Pesos</option>
                    <option value="dolares" <?php selected($currency, 'dolares'); ?>>Dólares</option>
                </select>
                <input type="number" min="1" step="any" id="amount" name="amount" value="<?php echo esc_attr($amount); ?>" placeholder="Monto a cobrar">
            </div>
        </fieldset>
    </div>
    <?php
}

add_action('woocommerce_process_shop_order_meta', 'paymentStatus');
function paymentStatus($orderId){
    if (isset($_POST['payment-status'])) {
        update_post_meta($orderId, '_payment-status', sanitize_text_field($_POST['payment-status']));
    }
    if (isset($_POST['payment-status']) && $_POST['payment-status'] === 'pending') {
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $currency = isset($_POST['currency']) ? sanitize_text_field($_POST['currency']) : 'No se selecciono la moneda';
        if ($amount > 0) {
            update_post_meta($orderId, '_amount', $amount);
            update_post_meta($orderId, '_currency', $currency);
        } else {
            delete_post_meta($orderId, '_amount');
            delete_post_meta($orderId, '_currency');
        }
    } else {
        delete_post_meta($orderId, '_amount');
        delete_post_meta($orderId, '_currency');
    }
}