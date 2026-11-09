<?php
/**
 * The notice after a status change from a quick action (?statusTransitionAction=success|error,
 * set by order-transitions.php).
 *
 * Moved from WPCodeBox › Order › Render Feedback Status Transition (snippet 45), which only ran
 * in wp-admin (same here). Only change: its match() is a lookup array, for PHP 7.4.
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_admin() ) {
	return;
}

add_action('admin_notices', 'renderFeedbackStatusTransitionActions');
function renderFeedbackStatusTransitionActions() {
    global $post;
    if (get_post_type($post) !== 'shop_order') return;
    // 1. Sanitizar la entrada
    $action = isset($_GET['statusTransitionAction']) ? sanitize_text_field($_GET['statusTransitionAction']) : null;

    if ($action) {
        // 2. Determinar mensaje (usando match o ternario limpio)
        // (A lookup instead of match(): this plugin supports PHP 7.4.)
        $messages = [
            'error'   => 'Debes seleccionar Pagado o Por Cobrar en el pedido antes de cambiar el estado.',
            'success' => 'El estado del pedido se cambió correctamente.',
        ];
        $message = $messages[$action] ?? 'Acción de la orden sin determinar';

        // 3. Determinar clase (Corregido el error de concatenación)
        $type_class = ($action === 'error') ? 'notice-error' : (($action === 'success') ? 'notice-success' : 'notice-info');
        $full_class = "notice is-dismissible my-notice " . $type_class;

        // 4. Imprimir con comillas correctas y escape de seguridad
        echo '<div class="' . esc_attr($full_class) . '"><p>' . esc_html($message) . '</p></div>';
    }
}
