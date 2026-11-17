<?php
/**
 * Order permissions per role: which statuses storage_manager and seo_manager see and which
 * transitions they can make, from where (quick, bulk, preview, order view, QR).
 *
 * From WPCodeBox › Roles › Config (snippet 56), where the rulesets were written by hand. They
 * now come from the configuration (config.php); this file gives them to the rest of the roles
 * feature in the shape it has always read, through getUserPermissions() and $global_flow:
 *
 *   [ '<status>' => bool, …, 'exclude_status_actions' => [ context => [ status, … ] ],
 *     'flow' => [ destination => [ origin, … ] ] ]
 */

defined( 'ABSPATH' ) || exit;

global $global_flow, $userPermissions;

$global_flow     = inversionesjl22_site_flow();
$userPermissions = array();

function setUserPermissions($username, $ruleset) {
    global $userPermissions;
    $userPermissions[$username] = $ruleset;
}

/**
 * The rules of the current user: null for an administrator (never restricted); the first of their
 * roles that is in the configuration; otherwise the closed rules below. Before, a role missing
 * from the configuration got null too, "no restrictions": a role created outside the plugin, or
 * one whose rules were lost, saw and moved every order. Now it starts without any.
 */
function getUserPermissions() {
    global $userPermissions;
    if (current_user_can('administrator')) return null;

    $user = wp_get_current_user();
    foreach ($user->roles as $role) {
        if (isset($userPermissions[$role])) return $userPermissions[$role];
    }
    return inversionesjl22_site_unconfigured_ruleset();
}

/**
 * The rules of a role the configuration does not have: every status off (written out, false,
 * because the status tabs treat a missing one as visible), no transitions of its own and no
 * "Combinar pedidos". So it sees no orders and has no status action of the plugin; the QR and the
 * shipping report also ask for a configured role (config.php, admin-menu.php). An administrator
 * adds the role in Inversiones JL22 › Roles y permisos to give it rules.
 */
function inversionesjl22_site_unconfigured_ruleset(): array {
    global $global_flow;
    $statuses = array_keys((array) (inversionesjl22_site_config()['statuses'] ?? []));
    foreach ((array) $global_flow as $to => $from) {
        $statuses[] = (string) $to;
        foreach ((array) $from as $origin) {
            $statuses[] = (string) $origin;
        }
    }
    $ruleset = array_fill_keys(array_unique($statuses), false);
    $ruleset['exclude_status_actions'] = ['bulk_actions' => ['combine_orders']];
    $ruleset['flow'] = [];
    return $ruleset;
}

/**
 * Whether the current user may use the plugin's screens for roles (the shipping report): an
 * administrator, or one of their roles is in the configuration.
 */
function inversionesjl22_site_user_has_configured_role(): bool {
    global $userPermissions;
    if (!is_user_logged_in()) return false;
    if (current_user_can('administrator')) return true;
    foreach ((array) wp_get_current_user()->roles as $role) {
        if (isset($userPermissions[$role])) return true;
    }
    return false;
}

foreach ( inversionesjl22_site_config()['roles'] as $inversionesjl22_site_role => $inversionesjl22_site_rules ) {
	setUserPermissions(
		$inversionesjl22_site_role,
		array_merge(
			array_map( 'boolval', (array) ( $inversionesjl22_site_rules['statuses'] ?? array() ) ),
			array(
				'exclude_status_actions' => (array) ( $inversionesjl22_site_rules['exclude'] ?? array() ),
				'flow'                   => inversionesjl22_site_flow( $inversionesjl22_site_role ),
			)
		)
	);
}
unset( $inversionesjl22_site_role, $inversionesjl22_site_rules );
