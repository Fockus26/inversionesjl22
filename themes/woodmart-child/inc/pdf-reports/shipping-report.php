<?php
/**
 * Controlador para generar reporte PDF de envíos.
 * Archivo: pdf-reports/shipping-report.php
 */

if (!defined('ABSPATH')) {
    exit; // Evitar acceso directo
}

// Cargar funciones necesarias
require_once __DIR__ . '/pdf-generator.php';

// Verificar que el usuario esté logueado y tenga permiso (ajusta permiso si quieres)
if (!is_user_logged_in() || !current_user_can('manage_woocommerce')) {
    wp_send_json_error('No tienes permiso para realizar esta acción.');
}

// Recibir parámetros (por ejemplo vía POST)
$start_date = $_POST['start_date'] ?? '';
$end_date   = $_POST['end_date'] ?? '';
$company_id = isset($_POST['company_id']) ? intval($_POST['company_id']) : 0;

// Validar fechas básicas
if (!$start_date || !$end_date) {
    wp_send_json_error('Debes proporcionar fecha de inicio y fin.');
}

// Llamar a la función que genera el PDF
$result = generate_shipping_report_pdf($start_date, $end_date, $company_id);

if (!$result) {
    wp_send_json_error('No se pudo generar el reporte o no hay datos.');
}

// Responder con URL del PDF generado
wp_send_json_success([
    'pdf_url' => $result['url'],
    'pdf_path' => $result['path'],
]);
