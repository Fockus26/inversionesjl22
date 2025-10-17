<?php
// Cargar funcionalidades de gestión de pedidos
require_once get_stylesheet_directory() . '/inc/order/custom-status.php';
require_once get_stylesheet_directory() . '/inc/order/transition-status.php';
require_once get_stylesheet_directory() . '/inc/order/change-shipping-method.php';
require_once get_stylesheet_directory() . '/inc/order/combined-orders.php';
require_once get_stylesheet_directory() . '/inc/order/payment-status.php';
require_once get_stylesheet_directory() . '/inc/roles/order-visibility.php';


// Cargar funcionalidades de reportes de envíos
require_once get_stylesheet_directory() . '/inc/report/pdf-shipping.php';
require_once get_stylesheet_directory() . '/inc/report/html-shipping.php';

// Cargar integraciones y utilidades
require_once get_stylesheet_directory() . '/inc/integrations/yith.php';
require_once get_stylesheet_directory() . '/inc/integrations/sku-generator.php';
require_once get_stylesheet_directory() . '/inc/integrations/enqueue.php';
require_once get_stylesheet_directory() . '/inc/integrations/admin-notices.php';
