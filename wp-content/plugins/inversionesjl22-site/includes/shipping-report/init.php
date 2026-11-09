<?php
/**
 * Shipping report (WooCommerce › Informe de Envíos): a form picks country, courier, dates and
 * shift; admin-post.php?action=shipping_report finds the completed orders and streams an 80 mm
 * PDF. Split in one file per WPCodeBox snippet, each moved as is.
 *
 * This file: the admin-post handler that queries the orders and asks for the PDF. From Shipping
 * Report › Init_1 (snippet 61).
 */

defined( 'ABSPATH' ) || exit;

 function shippingNormalize($string) {
    $string = remove_accents($string);
    $string = strtolower(trim($string));
    $string = str_replace(['"', "'", '“','”','`','´'], '', $string);
    $string = preg_replace('/\s+/', ' ', $string);
    return $string;
}

add_action('admin_post_shipping_report', 'shippingReport');
function shippingReport() {
    $user = wp_get_current_user();
    $allowedRoles = ['administrator', 'storage_manager', 'seo_manager'];

    $isUserAllowed = array_intersect($allowedRoles, (array) $user->roles);
    if (!$isUserAllowed) wp_die('No autorizado');

    global $wpdb;

    $country      = isset($_GET['country']) ? sanitize_text_field($_GET['country']) : '';
    $company      = isset($_GET['company']) ? sanitize_text_field($_GET['company']) : 'all';
    $startDate = isset($_GET['startDate']) ? sanitize_text_field($_GET['startDate']) : '';
    $endDate    = isset($_GET['endDate']) ? sanitize_text_field($_GET['endDate']) : '';
    $shift = isset($_GET['shift']) ? sanitize_text_field($_GET['shift']) : 'all_day';

    // --- Normalizar empresa recibida desde GET ---
    $queryFilterCompanies = "";
    $params = [];

    $queryFilterShift = "";

    if ($shift === 'morning') {
        $queryFilterShift = "
            AND TIME(CAST(pm_completed.meta_value AS DATETIME)) >= '00:00:00'
            AND TIME(CAST(pm_completed.meta_value AS DATETIME)) < '12:00:00'
        ";
    } elseif ($shift === 'evening') {
        $queryFilterShift = "
            AND TIME(CAST(pm_completed.meta_value AS DATETIME)) >= '12:00:00'
            AND TIME(CAST(pm_completed.meta_value AS DATETIME)) <= '23:59:59'
        ";
    }

    if ($company !== 'all') {
        // Normalizamos el valor recibido (PHP-side)
        $companies = stripslashes($company); // quita backslashes si vienen escapados
        $companies = trim($companies);
        // reemplaza comillas curvas por comilla recta
        $companies = str_replace(['“','”','`','´'], '"', $companies);
        // remover duplicados espacios
        $companies = preg_replace('/\s+/', ' ', $companies);

        $queryFilterCompanies = "AND TRIM(
            REPLACE(
                REPLACE(
                    REPLACE(oi.order_item_name, '“', '\"'),
                    '”', '\"'
                ),
                '\\\\\"', '\"'
            )
        ) = %s";

        $params[] = $companies;
    } else {
        // mantener comportamiento 'all' original
        $countryCompanies = fetchShippingCompaniesByCountry($country);
        if (!empty($countryCompanies)) {
            $placeholders = implode(',', array_fill(0, count($countryCompanies), '%s'));
            // Normalizar los valores que pasamos como parámetros
            $companies = array_map(function($company) {
                $company = stripslashes($company);
                $company = str_replace(['“','”','`','´'], '"', $company);
                return trim($company);
            }, $countryCompanies);

            $queryFilterCompanies = "AND TRIM(
                REPLACE(
                    REPLACE(
                        REPLACE(oi.order_item_name, '“', '\"'),
                        '”', '\"'
                    ),
                    '\\\\\"', '\"'
                )
            ) IN ($placeholders)";
            $params = array_merge($params, $companies);
        }
    }

    // Filtro de país y fechas
    $params = array_merge([$country, $startDate . ' 00:00:00', $endDate . ' 23:59:59'], $params);

    // Query para obtener IDs de pedidos (casteando completed_date)
    $query = "
      SELECT DISTINCT p.ID
      FROM {$wpdb->prefix}posts p
      INNER JOIN {$wpdb->prefix}postmeta pm_country
          ON p.ID = pm_country.post_id
          AND pm_country.meta_key = '_shipping_country'
          AND pm_country.meta_value = %s
      INNER JOIN {$wpdb->prefix}postmeta pm_completed
          ON p.ID = pm_completed.post_id
          AND pm_completed.meta_key = '_completed_date'
      INNER JOIN {$wpdb->prefix}woocommerce_order_items oi
          ON p.ID = oi.order_id
          AND oi.order_item_type = 'shipping'
      WHERE p.post_type = 'shop_order'
        AND p.post_status = 'wc-completed'
        AND CAST(pm_completed.meta_value AS DATETIME) BETWEEN %s AND %s
        $queryFilterShift
        $queryFilterCompanies
      ORDER BY pm_completed.meta_value DESC
    ";

    $orderIds = $wpdb->get_col($wpdb->prepare($query, ...$params));

    $matchedCompany = stripslashes($company);
    $needle = shippingNormalize($matchedCompany);

    // Obtener objetos de pedido y filtrar por coincidencia robusta
    $orders = [];
    foreach ($orderIds as $orderId) {
        $order = wc_get_order($orderId);
        if (!$order) continue;

        $matches = false;

        $shippingItems = $order->get_items('shipping');
        foreach ($shippingItems as $shippingItem) {
            $rawMethod = (string) $shippingItem->get_name();
            $method = shippingNormalize($rawMethod);

            if ($company !== 'all') {
                if ($needle !== '' && (strpos($method, $needle) !== false || $method === $needle)) {
                    $matches = true;
                    break;
                }
            } else {
                $matches = true;
                break;
            }
        }

        // fallback: comparar con meta _shipping_company si existe
        if (!$matches) {
            $companyMeta = (string) get_post_meta($orderId, '_shipping_company', true);
            if ($companyMeta !== '' && strpos(shippingNormalize($companyMeta), $needle) !== false) {
                $matches = true;
            }
        }

        if ($matches) {
            $orders[] = $order;
        }
    }

    $countries = [
        'VE' => 'Venezuela',
        'CO' => 'Colombia',
    ];

    // Convertir el código de país a nombre legible
    $country = $countries[$country];

    // Si es "all", mostrar "Todo el país", no el nombre del país
    $company = ($company === 'all') ? 'Todo el país' : $company;

    // Generar el PDF con la acción existente
    do_action('wpo_wcpdf_custom_report_pdf', [
        'company' => $company,
        'startDate'   => $startDate,
        'endDate'      => $endDate,
        'orders'        => $orders,
        'country'           => $country
    ]);
    exit;
}
