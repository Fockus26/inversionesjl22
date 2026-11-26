<?php
/**
 * Demo copy only (mounted by docker-compose.demo.yml): keeps wp-admin free of the notices that
 * are noise in a demo, the core update nag above every screen. The copy stays on production's
 * WordPress version on purpose.
 */

add_action(
	'admin_init',
	static function () {
		remove_action( 'admin_notices', 'update_nag', 3 );
		remove_action( 'network_admin_notices', 'update_nag', 3 );
	}
);
