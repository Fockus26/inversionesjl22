<?php
/**
 * "Estado de pago" for cash-on-delivery orders (shipping methods in getPickUpShippingMethods()):
 * Pagado / Por cobrar, and for "Por cobrar" the currency (pesos / dólares) and the amount to
 * collect. Saved with the order as _payment-status, _currency and _amount; the status
 * transitions require it, and the invoice templates print it.
 *
 * Moved as is from WPCodeBox › Order › Payment Status (snippet 43, which only ran in wp-admin:
 * same here) and Payment Status_1 (snippet 46, the JS that shows currency and amount only for
 * "Por cobrar"; WPCodeBox printed it as a <script> in every wp-admin <head>, here it is
 * enqueued in the same place). Only change since: the "Moneda" label pointed at the status
 * select and the amount had no label (WCAG 1.3.1, 4.1.2); now each field has its own.
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_admin() ) {
	return;
}

add_action(
	'admin_enqueue_scripts',
	static function () {
		if ( 'order' !== inversionesjl22_site_admin_screen() ) {
			return;
		}
		wp_enqueue_script(
			'inversionesjl22-payment-status',
			INVERSIONESJL22_SITE_URL . 'assets/js/payment-status.js',
			array(),
			inversionesjl22_site_asset_version( 'assets/js/payment-status.js' ),
			false
		);
	}
);

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
                <label for="currency">Moneda</label>
                <select id="currency" name="currency">
                    <option value="pesos" <?php selected($currency, 'pesos'); ?>>Pesos</option>
                    <option value="dolares" <?php selected($currency, 'dolares'); ?>>Dólares</option>
                </select>
                <label for="amount">Monto a cobrar</label>
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
