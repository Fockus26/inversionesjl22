<?php
/**
 * "Limpiar carrito": a button in the cart's actions, and ?clear-cart=1 empties the cart and
 * goes back to it.
 *
 * Moved from WPCodeBox › Boton Limpiar Carrito (snippet 42). Only change: the link carries a nonce
 * and the handler checks it, so a link from another site cannot empty a visitor's cart.
 */

defined( 'ABSPATH' ) || exit;

add_action('woocommerce_cart_actions', function () {
    if (WC()->cart->is_empty()) {
        return;
    }

    echo '<a href="' . esc_url(wp_nonce_url(add_query_arg('clear-cart', '1', wc_get_cart_url()), 'jl22_clear_cart')) . '" class="button clear-cart">Limpiar carrito</a>';
});

add_action('wp_loaded', function () {
    if (
        isset($_GET['clear-cart']) &&
        $_GET['clear-cart'] === '1' &&
        isset($_GET['_wpnonce']) &&
        wp_verify_nonce(sanitize_key(wp_unslash($_GET['_wpnonce'])), 'jl22_clear_cart') &&
        function_exists('WC') &&
        WC()->cart
    ) {
        WC()->cart->empty_cart();

        wp_safe_redirect(wc_get_cart_url());
        exit;
    }
});
