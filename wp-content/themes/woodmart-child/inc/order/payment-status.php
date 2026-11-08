<?php
/**
 * ===========================================
 * CAMPOS PERSONALIZADOS DE ESTADO DE PAGO JL22
 * ===========================================
 * Agrega un selector de estado de pago (Pagado/Por Cobrar)
 * y campos de monto/divisa en la edición de pedidos WooCommerce.
 * Solo se muestra si el método de envío es uno de los permitidos.
 * La información se guarda como meta en el pedido.
 */
add_action('woocommerce_admin_order_data_after_shipping_address', function($order){
    $shipping_methods = [
        'CONTRA ENTREGA EFECTIVO',
        'DOMICILIO CUCUTA',
        'DOMICILIO SAN CRISTOBAL',
        'ENTREGAS TEALCA'
    ];

    $order_shipping = $order->get_shipping_methods();
    $show = false;
    foreach ($order_shipping as $method) {
        if (in_array($method->get_method_title(), $shipping_methods)) {
            $show = true;
            break;
        }
    }
    if (!$show) return;

    $status = get_post_meta($order->get_id(), '_jl_pago_status', true);
    $amount = get_post_meta($order->get_id(), '_jl_pago_amount', true);
    $currency = get_post_meta($order->get_id(), '_jl_pago_currency', true);

    ?>
    <div style="margin-top:20px;">
        <h4>Estado de Pago</h4>
        <select id="jl_pago_status" name="jl_pago_status">
            <option value="">--- Seleccione ---</option>
            <option value="pagado" <?php selected($status, 'pagado'); ?>>Pagado</option>
            <option value="por_cobrar" <?php selected($status, 'por_cobrar'); ?>>Por Cobrar</option>
        </select>
        <div id="jl_pago_extra" style="margin-top:10px;<?php echo ($status == 'por_cobrar') ? '' : 'display:none;'; ?>">
            <select id="jl_pago_currency" name="jl_pago_currency" style="width:auto;display:inline-block;">
                <option value="pesos" <?php selected($currency, 'pesos'); ?>>Pesos</option>
                <option value="dolares" <?php selected($currency, 'dolares'); ?>>Dólares</option>
            </select>
            <input type="number" min="1" step="any" id="jl_pago_amount" name="jl_pago_amount" value="<?php echo esc_attr($amount); ?>" placeholder="Monto a cobrar" style="width:120px;display:inline-block;">
        </div>
        <script>
        jQuery(function($){
            $('#jl_pago_status').on('change', function(){
                if($(this).val() === 'por_cobrar'){
                    $('#jl_pago_extra').show();
                }else{
                    $('#jl_pago_extra').hide();
                }
            });
        });
        </script>
    </div>
    <?php
});

add_action('woocommerce_process_shop_order_meta', function($order_id){
    if (isset($_POST['jl_pago_status'])) {
        update_post_meta($order_id, '_jl_pago_status', sanitize_text_field($_POST['jl_pago_status']));
    }
    if (isset($_POST['jl_pago_status']) && $_POST['jl_pago_status'] === 'por_cobrar') {
        $amount = isset($_POST['jl_pago_amount']) ? floatval($_POST['jl_pago_amount']) : 0;
        if ($amount > 0) {
            update_post_meta($order_id, '_jl_pago_amount', $amount);
            update_post_meta($order_id, '_jl_pago_currency', sanitize_text_field($_POST['jl_pago_currency']));
        } else {
            delete_post_meta($order_id, '_jl_pago_amount');
            delete_post_meta($order_id, '_jl_pago_currency');
        }
    } else {
        delete_post_meta($order_id, '_jl_pago_amount');
        delete_post_meta($order_id, '_jl_pago_currency');
    }
});