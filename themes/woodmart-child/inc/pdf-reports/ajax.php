<?php
/**
 * AJAX handlers para reportes PDF
 * Archivo: pdf-reports/ajax.php
 */

// Aseguramos que WordPress esté cargado
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX: Obtener lista de empresas (por ejemplo, para un filtro en el reporte)
 */
add_action('wp_ajax_get_company_list', function () {
    // Verificar permisos (puedes ajustar según rol)
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('No tienes permiso');
        wp_die();
    }

    // Ejemplo simple: obtener posts de tipo 'company' o un custom post type o taxonomy
    // Aquí ajusta según tu estructura real
    $companies = get_terms([
        'taxonomy' => 'empresa', // ejemplo: taxonomy personalizada 'empresa'
        'hide_empty' => false,
    ]);

    if (is_wp_error($companies)) {
        wp_send_json_error('Error al obtener empresas');
        wp_die();
    }

    // Formatear la lista para enviar
    $list = [];
    foreach ($companies as $company) {
        $list[] = [
            'id' => $company->term_id,
            'name' => $company->name,
        ];
    }

    wp_send_json_success($list);
    wp_die();
});

/**
 * AJAX: Generar reporte PDF de envíos
 */
add_action('wp_ajax_generate_shipping_report_pdf', function () {
    // Verificar nonce y permisos
    check_ajax_referer('generate_shipping_report', 'nonce');

    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('No tienes permiso');
        wp_die();
    }

    // Recibir parámetros de filtro del reporte (fechas, empresa, etc)
    $start_date = sanitize_text_field($_POST['start_date'] ?? '');
    $end_date = sanitize_text_field($_POST['end_date'] ?? '');
    $company_id = intval($_POST['company_id'] ?? 0);

    // Validar fechas (puedes agregar validaciones más estrictas)
    if (!$start_date || !$end_date) {
        wp_send_json_error('Fechas inválidas');
        wp_die();
    }

    // Aquí llamarías a la función que genera el PDF con esos filtros,
    // por ejemplo una función en pdf-generator.php
    $pdf_content = generate_shipping_report_pdf($start_date, $end_date, $company_id);

    if (!$pdf_content) {
        wp_send_json_error('Error generando reporte');
        wp_die();
    }

    // Enviar la respuesta con la URL o contenido base64 para descargar o mostrar
    wp_send_json_success([
        'pdf_url' => $pdf_content['url'] ?? '',
        // o 'pdf_base64' => $pdf_content['base64'] si devuelves el contenido
    ]);

    wp_die();
});
