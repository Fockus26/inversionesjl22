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

function getUserPermissions() {
    global $userPermissions;
    if (current_user_can('administrator')) return null;

    $user = wp_get_current_user();
    foreach ($user->roles as $role) {
        if (isset($userPermissions[$role])) return $userPermissions[$role];
    }
    return null;
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
