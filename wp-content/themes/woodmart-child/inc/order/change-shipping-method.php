<?php
// Obtiene TODOS los métodos de envío activos de todas las zonas
function jl22_obtener_todos_los_metodos_envio() {
    $metodos = [];

    // Zonas personalizadas
    $zonas = WC_Shipping_Zones::get_zones();
    foreach ($zonas as $zona) {
        $zone_obj = new WC_Shipping_Zone($zona['zone_id']);
        $methods = $zone_obj->get_shipping_methods(true);
        foreach ($methods as $method) {
            if ($method->enabled === 'yes') {
                $instance_key = $method->id . ':' . $method->get_instance_id();
                $metodos[$instance_key] = $method->get_title();
            }
        }
    }

    // Zona "rest of the world"
    $rest_zone = new WC_Shipping_Zone(0);
    foreach ($rest_zone->get_shipping_methods(true) as $method) {
        if ($method->enabled === 'yes') {
            $instance_key = $method->id . ':' . $method->get_instance_id();
            $metodos[$instance_key] = $method->get_title();
        }
    }

    return $metodos;
}

// Mostrar select de método de envío en edición de pedido
add_action('woocommerce_admin_order_data_after_shipping_address', function($order){
    // Obtener items de envío del pedido
    $shipping_items = $order->get_items('shipping');
    $current_method = '';
    if (!empty($shipping_items)) {
        $first_item = reset($shipping_items);
        $current_method = $first_item->get_method_title();
    }

    // Obtener todos los métodos activos sin importar país
    $available_methods = jl22_obtener_todos_los_metodos_envio();

    ?>
    <p class="form-field form-field-wide">
        <label for="jl22_shipping_method">Método de envío:</label>
        <select name="jl22_shipping_method" id="jl22_shipping_method">
            <?php foreach ($available_methods as $id => $title): ?>
                <option value="<?php echo esc_attr($id); ?>" <?php selected($current_method, $title); ?>>
                    <?php echo esc_html($title); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>
    <?php
});

// Guardar cambios de método de envío al actualizar pedido
add_action('woocommerce_process_shop_order_meta', function($order_id){
    if (empty($_POST['jl22_shipping_method'])) return;

    $order = wc_get_order($order_id);
    if (!$order) return;

    $new_method_id = sanitize_text_field($_POST['jl22_shipping_method']);

    // Buscar el título del método
    $available_methods = jl22_obtener_todos_los_metodos_envio();
    if (!isset($available_methods[$new_method_id])) return;

    $new_title = $available_methods[$new_method_id];

    // Eliminar shipping items previos
    foreach ($order->get_items('shipping') as $item_id => $item) {
        $order->remove_item($item_id);
    }

    // Agregar nuevo método de envío
    $item = new WC_Order_Item_Shipping();
    $item->set_method_title($new_title);
    $item->set_method_id($new_method_id);
    $item->set_total(0); // ⚠️ Aquí podrías calcular costo real si aplica
    $order->add_item($item);

    $order->calculate_totals();
    $order->save();
});