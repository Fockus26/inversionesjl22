<?php
/**
 * ========================
 * HELPERS GENERALES DEL TEMA
 * ========================
 */

/**
 * Obtiene las reglas de permisos para el usuario actual basadas en su rol.
 *
 * @return array|null Reglas o null si no hay reglas para el rol.
 */
function get_current_user_ruleset() {
    if (!is_user_logged_in()) {
        return null;
    }

    $user = wp_get_current_user();
    $roles = (array) $user->roles;

    // Accedemos a la configuración global
    $all_rules = $GLOBALS['jl22_roles_permissions'] ?? [];

    foreach ($roles as $role) {
        if (isset($all_rules[$role])) {
            return $all_rules[$role];
        }
    }

    return null;
}
