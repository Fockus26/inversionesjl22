<?php 
add_action('woocommerce_cart_actions', function () {
    if (WC()->cart->is_empty()) {
        return;
    }

    echo '<a href="' . esc_url(wc_get_cart_url() . '?clear-cart=1') . '" class="button clear-cart">Limpiar carrito</a>';
});

add_action('wp_loaded', function () {
    if (
        isset($_GET['clear-cart']) &&
        $_GET['clear-cart'] === '1' &&
        function_exists('WC') &&
        WC()->cart
    ) {
        WC()->cart->empty_cart();

        wp_safe_redirect(wc_get_cart_url());
        exit;
    }
});