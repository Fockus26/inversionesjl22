<?php
/**
 * "Método de envío" in the order: a select with every enabled shipping method of every zone,
 * and on save the order's shipping line is replaced by the chosen one (cost 0) and the totals
 * recalculated.
 *
 * Moved as is from WPCodeBox › Order › Change Shipping Method (snippet 48), which only ran in
 * wp-admin: same here.
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_admin() ) {
	return;
}

function fetchShippingMethods() {
    $methods = [];

    // Zonas personalizadas
    $zones = WC_Shipping_Zones::get_zones();
    foreach ($zones as $zone) {
        $zoneObject = new WC_Shipping_Zone($zone['zone_id']);
        $zoneMethods = $zoneObject->get_shipping_methods(true);
        foreach ($zoneMethods as $zoneMethod) {
            if ($zoneMethod->enabled === 'yes') {
                $instance = $zoneMethod->id . ':' . $zoneMethod->get_instance_id();
                $methods[$instance] = $zoneMethod->get_title();
            }
        }
    }

    // Zona "rest of the world"
    $restZone = new WC_Shipping_Zone(0);
    foreach ($restZone->get_shipping_methods(true) as $method) {
        if ($method->enabled === 'yes') {
            $instance = $method->id . ':' . $method->get_instance_id();
            $methods[$instance] = $method->get_title();
        }
    }

    return $methods;
}

add_action('woocommerce_admin_order_data_after_shipping_address', 'changeShippingMethodUI');
function changeShippingMethodUI($order){
    $shippingItems = $order->get_items('shipping');
    $currentMethod = '';
    if (!empty($shippingItems)) {
        $firstItem = reset($shippingItems);
        $currentMethod = $firstItem->get_method_title();
    }

    $methods = fetchShippingMethods();


    ?>
    <div class="my-form">
        <label for="method">Método de envío</label>
        <select name="method" id="method">
            <?php foreach ($methods as $id => $title): ?>
                <option value="<?php echo esc_attr($id); ?>" <?php selected($currentMethod, $title); ?>>
                    <?php echo esc_html($title); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php
}

add_action('woocommerce_process_shop_order_meta', 'changeShippingMethod');
function changeShippingMethod($orderId){
    if (empty($_POST['method'])) return;

    $order = wc_get_order($orderId);
    if (!$order) return;

    $newMethodId = sanitize_text_field($_POST['method']);

    // Buscar el título del método
    $methods = fetchShippingMethods();
    if (!isset($methods[$newMethodId])) return;

    $newTitle = $methods[$newMethodId];

    // Eliminar shipping items previos
    foreach ($order->get_items('shipping') as $item_id => $item) {
        $order->remove_item($item_id);
    }

    // Agregar nuevo método de envío
    $item = new WC_Order_Item_Shipping();
    $item->set_method_title($newTitle);
    $item->set_method_id($newMethodId);
    $item->set_total(0);
    $order->add_item($item);

    $order->calculate_totals();
    $order->save();
}
