<?php
/**
 * ============================
 * ROLES PERMISSIONS CONFIG FILE
 * ============================
 *
 * Define reglas de permisos para roles personalizados,
 * controla a qué estados pueden transicionar y qué ven.
 */

if (!function_exists('limit_user_permissions')) {
    function limit_user_permissions($role, $rules) {
        // Aquí podrías guardar las reglas en un lugar accesible,
        // por ejemplo un array global o una opción si quieres persistencia.
        // Para simplicidad, usamos una variable estática:
        static $all_rules = [];

        $all_rules[$role] = $rules;

        // También guardamos en global para acceso en get_current_user_ruleset()
        $GLOBALS['jl22_roles_permissions'] = $all_rules;
    }
}

// Configuración para el rol seo_manager
limit_user_permissions('seo_manager', [
    'to_processing'  => true,
    'to_impressed'   => false,
    'to_completed'   => false,
    'to_cancelled'   => true,
    'can_view_order' => true,
    'filter_invoice_status' => [],
    'extra_view_status' => ['completed'],
]);

// Configuración para el rol storage_manager
limit_user_permissions('storage_manager', [
    'to_processing'  => false,
    'to_impressed'   => true,
    'to_completed'   => true,
    'to_cancelled'   => false,
    'can_view_order' => false,
    'filter_invoice_status' => ['on-hold', 'completed', 'cancelled'],
    'extra_view_status' => [],
]);
