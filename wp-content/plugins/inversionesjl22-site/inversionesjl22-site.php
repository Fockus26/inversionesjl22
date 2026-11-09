<?php
/**
 * Plugin Name: Inversionesjl22 Site
 * Description: All of the site's code (web-agent-kit). Code lives here, in git, never in snippets.
 * Version:     0.1.0
 * Requires PHP: 7.4
 * Text Domain: inversionesjl22-site
 *
 * new-project.ps1 -Stack wordpress -Client <client> renames "acme" in this folder. By hand:
 * folder and file `<client>-site`, functions `<client>_site_`, constants `<CLIENT>_SITE_`.
 */

defined( 'ABSPATH' ) || exit;

define( 'INVERSIONESJL22_SITE_VERSION', '0.1.0' );
define( 'INVERSIONESJL22_SITE_FILE', __FILE__ );
define( 'INVERSIONESJL22_SITE_DIR', plugin_dir_path( __FILE__ ) );
define( 'INVERSIONESJL22_SITE_URL', plugin_dir_url( __FILE__ ) );

// One file per feature. Listed by hand, not globbed: the order is explicit and a stray file
// left on the server by a deploy is never loaded.
require_once INVERSIONESJL22_SITE_DIR . 'includes/assets.php';
require_once INVERSIONESJL22_SITE_DIR . 'includes/acf-json.php';
require_once INVERSIONESJL22_SITE_DIR . 'includes/migrations.php';

/*
 * Features moved from WPCodeBox (context/plans/adoption.md). Until the deploy that deactivates
 * WPCodeBox, the same functions are still declared by its snippets, and declaring them twice is
 * a fatal error. So each module loads at the very end of plugins_loaded (after WPCodeBox has
 * run its snippets) and only if its marker function is not there yet: while the snippet is
 * active, WPCodeBox's copy runs; once it is deactivated, this one takes over.
 *
 * file => a function the module (and its snippet) declares, or '' for a module that guards
 * each of its functions itself (helpers.php: two snippets, one function each).
 *
 * Listed in the order WPCodeBox loaded the snippets (wpcodebox_functionality_plugin/plugin.php):
 * hooks on the same priority run in that order, and some depend on it (the roles filters
 * rebuild the actions order-statuses.php adds).
 */
add_action(
	'plugins_loaded',
	static function () {
		$modules = array(
			'includes/helpers.php'                    => '',
			'includes/order-statuses.php'             => 'createCustomStatus',
			'includes/order-transition-notices.php'   => 'renderFeedbackStatusTransitionActions',
			'includes/roles-config.php'               => 'setUserPermissions',
			'includes/roles-actions.php'              => 'getStatusAjaxAction',
			'includes/roles-access.php'               => 'authorizeOrderView',
			'includes/customer-status.php'            => 'get_custom_user_statuses',
			'includes/order-transitions.php'          => 'orderStatusTransition',
		);
		foreach ( $modules as $file => $marker ) {
			if ( '' === $marker || ! function_exists( $marker ) ) {
				require_once INVERSIONESJL22_SITE_DIR . $file;
			}
		}
	},
	PHP_INT_MAX
);
