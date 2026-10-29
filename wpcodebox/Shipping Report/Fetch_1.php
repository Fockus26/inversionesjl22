<?php 
function fetchShippingCompaniesByCountry($country) {
    $companies = [];

    // Obtenemos todas las zonas de envío
    $zones = WC_Shipping_Zones::get_zones();

    foreach ($zones as $zone) {
        $zone = new WC_Shipping_Zone($zone['zone_id']);
        $locations = $zone->get_zone_locations();

        // Revisamos si esta zona aplica al país dado
        foreach ($locations as $location) {
            if ($location->type === 'country' && $location->code === $country) {
                // Obtener métodos activos de la zona
                $methods = $zone->get_shipping_methods(true, 'values');
                foreach ($methods as $method) {
                    if ($method->enabled === 'yes') {
                        $companies[] = $method->get_title();
                    }
                }
            }
        }
    }

    return $companies;
}

// AJAX: Devuelve empresas de envío válidas para un país, filtradas por las definidas
add_action('wp_ajax_fetch_shipping_companies', 'ajaxFetchShippingCompanies');
function ajaxFetchShippingCompanies() {
    // Requiere usuario con rol
    $user = wp_get_current_user();
    $allowedRoles = ['administrator', 'storage_manager', 'seo_manager'];
    if ( ! array_intersect( $allowedRoles, (array) $user->roles ) ) {
        wp_send_json_error(['message' => 'No autorizado'], 403);
    }

    $country = sanitize_text_field( $_GET['pais'] ?? '' );
    if ( empty( $country ) ) {
        wp_send_json_error(['message' => 'País vacío'], 400);
    }

    // Cache: intenta recuperar transients por país
    $countryCompanies = get_transient($transient_key);

    if ($countryCompanies === false) {
        $countryCompanies = fetchShippingCompaniesByCountry($country);
    }

    if ( empty( $countryCompanies ) ) {
        wp_send_json_success(['empresas' => []]);
    }

    // Obtener empresas presentes en pedidos recientes (consulta ligera)
    global $wpdb;
    $query = $wpdb->prepare("
        SELECT DISTINCT oi.order_item_name
        FROM {$wpdb->prefix}woocommerce_order_items oi
        INNER JOIN {$wpdb->prefix}posts p ON oi.order_id = p.ID
        INNER JOIN {$wpdb->prefix}postmeta pm_country ON pm_country.post_id = p.ID
            AND pm_country.meta_key = '_shipping_country'
        WHERE oi.order_item_type = 'shipping'
          AND pm_country.meta_value = %s
          AND p.post_type = 'shop_order'
          AND p.post_status IN ('wc-processing','wc-completed','wc-impreso')
    ", $country);

    $queryCompanies = $wpdb->get_col($query);

    $companies = [];
    foreach ($queryCompanies as $queryCompany) {
        foreach ($countryCompanies as $countryCompany) {
            if (strcasecmp($queryCompany, $countryCompany) === 0) {
                $companies[] = $countryCompany;
                break;
            }
        }
    }

    wp_send_json_success(['empresas' => array_values(array_unique($companies))]);
}
