<?php
/**
 * "Status del Cliente" (Estándar, Nuevo, Premium, Selecto, Patrocinio): a select in the order
 * and in the order preview, saved in the customer's user meta (or the order's, for guests)
 * as _jl_customer_status.
 *
 * Moved as is from WPCodeBox › User › Custom Status (snippet 64).
 */

if (!defined('ABSPATH')) {
    exit;
}

function get_custom_user_statuses() {
    return [
        'standard'      => 'Estándar',
        'rookie'        => 'Nuevo',
        'premium'       => 'Premium',
        'selecto'       => 'Selecto',
        'sponsorship'   => 'Patrocinio',
    ];
}

/**
 * Mostrar select en la orden
 */
add_action(
    'woocommerce_admin_order_data_after_order_details',
    'render_custom_user_statuses'
);

function render_custom_user_statuses($order) {
    $userId = $order->get_user_id();

    // Si hay usuario: sacar status desde user_meta
    if ($userId) {

        $currentStatus = get_user_meta(
            $userId,
            '_jl_customer_status',
            true
        );

    } else {
        // Si NO hay usuario: sacar status desde order_meta
        $currentStatus = $order->get_meta(
            '_jl_customer_status',
            true
        );
    }

    // Default
    if (empty($currentStatus)) {
        $currentStatus = 'standard';
    }

    $statuses = get_custom_user_statuses();
    ?>

    <div class="my-form form-field form-field-wide">
        <label
            for="custom_user_status"
        >
            Status del Cliente
        </label>

        <select
            name="custom_user_status"
            id="custom_user_status"
        >
            <?php foreach ($statuses as $value => $label): ?>
                <option
                    value="<?php echo esc_attr($value); ?>"
                    <?php selected($currentStatus, $value); ?>
                >
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php
}

/**
 * Guardar status del usuario
 */
add_action(
    'woocommerce_process_shop_order_meta',
    'save_custom_user_status'
);

function save_custom_user_status($orderId) {
    if (!isset($_POST['custom_user_status'])) {
        return;
    }

    $status = sanitize_text_field(
        $_POST['custom_user_status']
    );

    $validStatuses = array_keys(
        get_custom_user_statuses()
    );

    if (!in_array($status, $validStatuses, true)) {
        return;
    }

    $order = wc_get_order($orderId);

    if (!$order) {
        return;
    }

    $userId = $order->get_user_id();

    /**
     * Si hay usuario:
     * guardar en user_meta
     */
    if ($userId) {
        update_user_meta(
            $userId,
            '_jl_customer_status',
            $status
        );

    } else {
        /**
         * Invitado:
         * guardar en order_meta
         */
        $order->update_meta_data(
            '_jl_customer_status',
            $status
        );

        $order->save();
    }
}

add_filter(
    'woocommerce_admin_order_preview_get_order_details',
    'jl_add_customer_status_to_preview',
    10,
    2
);

function jl_add_customer_status_to_preview($order_details, $order) {

    $userId = $order->get_user_id();

    if ($userId) {

        $currentStatus = get_user_meta(
            $userId,
            '_jl_customer_status',
            true
        );

    } else {

        $currentStatus = $order->get_meta(
            '_jl_customer_status',
            true
        );
    }

    if (empty($currentStatus)) {
        $currentStatus = 'standard';
    }

    $order_details['jl_customer_status'] = $currentStatus;

    return $order_details;
}

add_action(
    'woocommerce_admin_order_preview_end',
    'jl_render_customer_status_preview'
);

function jl_render_customer_status_preview() {

    ?>

    <div
        class="jl-customer-status-preview"
        data-customer-status-preview
    >

        <label>
            <strong>Status del Cliente</strong>
        </label>

        <select
            class="jl-customer-status-select"
            data-order-id="{{data.data.id}}"
        >
            <?php foreach (get_custom_user_statuses() as $value => $label): ?>

                <option
                    value="<?php echo esc_attr($value); ?>"
                >
                    <?php echo esc_html($label); ?>
                </option>

            <?php endforeach; ?>
        </select>

    </div>

    <?php
}


/**
 * ============================================================
 * AJAX - GUARDAR STATUS
 * ============================================================
 */

add_action(
    'wp_ajax_jl_update_customer_status',
    'jl_update_customer_status'
);

function jl_update_customer_status() {

    check_ajax_referer(
        'jl_update_customer_status',
        'nonce'
    );

    if (!current_user_can('edit_shop_orders')) {

        wp_send_json_error([
            'message' => 'No tienes permisos para modificar esta orden.'
        ]);
    }

    $orderId = isset($_POST['order_id'])
        ? absint($_POST['order_id'])
        : 0;

    $status = isset($_POST['status'])
        ? sanitize_text_field($_POST['status'])
        : '';

    $order = wc_get_order($orderId);

    if (!$order) {

        wp_send_json_error([
            'message' => 'Orden no encontrada.'
        ]);
    }

    $validStatuses = array_keys(
        get_custom_user_statuses()
    );

    if (!in_array($status, $validStatuses, true)) {

        wp_send_json_error([
            'message' => 'Status inválido.'
        ]);
    }

    $userId = $order->get_user_id();

    if ($userId) {

        update_user_meta(
            $userId,
            '_jl_customer_status',
            $status
        );

    } else {

        $order->update_meta_data(
            '_jl_customer_status',
            $status
        );

        $order->save();
    }

    wp_send_json_success([
        'status' => $status
    ]);
}

add_action(
    'admin_enqueue_scripts',
    'jl_customer_status_preview_script'
);

// The preview's select saves on change (assets/js/customer-status.js). Only in the orders list,
// where the preview opens; WPCodeBox printed it inline in every wp-admin footer. Its styles
// are in assets/css/admin-form.css.
function jl_customer_status_preview_script() {
    if ('orders' !== inversionesjl22_site_admin_screen()) {
        return;
    }
    wp_enqueue_script(
        'inversionesjl22-customer-status',
        INVERSIONESJL22_SITE_URL . 'assets/js/customer-status.js',
        ['jquery'],
        inversionesjl22_site_asset_version('assets/js/customer-status.js'),
        true
    );
    wp_localize_script('inversionesjl22-customer-status', 'jlCustomerStatus', [
        'nonce' => wp_create_nonce('jl_update_customer_status'),
    ]);
}


/**
 * ============================================================
 * OBTENER STATUS ACTUAL
 * ============================================================
 */

add_action(
    'wp_ajax_jl_get_customer_status',
    'jl_get_customer_status'
);

function jl_get_customer_status() {

    check_ajax_referer(
        'jl_get_customer_status',
        'nonce'
    );

    if (!current_user_can('edit_shop_orders')) {

        wp_send_json_error([
            'message' => 'No tienes permisos.'
        ]);
    }

    $orderId = isset($_POST['order_id'])
        ? absint($_POST['order_id'])
        : 0;

    $order = wc_get_order($orderId);

    if (!$order) {

        wp_send_json_error([
            'message' => 'Orden no encontrada.'
        ]);
    }

    $userId = $order->get_user_id();

    if ($userId) {

        $currentStatus = get_user_meta(
            $userId,
            '_jl_customer_status',
            true
        );

    } else {

        $currentStatus = $order->get_meta(
            '_jl_customer_status',
            true
        );
    }

    if (empty($currentStatus)) {
        $currentStatus = 'standard';
    }

    wp_send_json_success([
        'status'  => $currentStatus,
        'options' => get_custom_user_statuses()
    ]);
}
