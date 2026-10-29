<?php 
add_action('admin_notices', 'renderFeedbackStatusTransitionActions');
function renderFeedbackStatusTransitionActions() {
    global $post;
    if (get_post_type($post) !== 'shop_order') return;
    // 1. Sanitizar la entrada
    $action = isset($_GET['statusTransitionAction']) ? sanitize_text_field($_GET['statusTransitionAction']) : null;

    if ($action) {
        // 2. Determinar mensaje (usando match o ternario limpio)
        $message = match($action) {
            'error'   => 'Debes seleccionar Pagado o Por Cobrar en el pedido antes de cambiar el estado.',
            'success' => 'El estado del pedido se cambió correctamente.',
            default   => 'Acción de la orden sin determinar',
        };

        // 3. Determinar clase (Corregido el error de concatenación)
        $type_class = ($action === 'error') ? 'notice-error' : (($action === 'success') ? 'notice-success' : 'notice-info');
        $full_class = "notice is-dismissible my-notice " . $type_class;

        // 4. Imprimir con comillas correctas y escape de seguridad
        echo '<div class="' . esc_attr($full_class) . '"><p>' . esc_html($message) . '</p></div>';
    }
}