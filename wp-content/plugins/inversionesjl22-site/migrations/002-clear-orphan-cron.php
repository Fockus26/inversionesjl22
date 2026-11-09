<?php
/**
 * Unschedules the cron events of plugins that are no longer installed (context/plans/adoption.md
 * §5): WordPress keeps running them every hour or day, each one a call that does nothing. Each
 * prefix below was checked against wp-content (plugins, mu-plugins, themes) of the copy of
 * production: no code registers it any more. The events removed are remembered, with their
 * schedule and arguments, so undoing it is scheduling them again.
 *
 * Not touched: hooks of installed plugins, active or not (the puc_ and check_plugin_updates-
 * ones of the premium plugins, sb_instagram and sbi, simple_history, yith_), GoDaddy's (wpaas_,
 * mwp_) and Object Cache Pro's (objectcache_), whose mu-plugin is active in production; and two
 * the plan listed but are alive: sbc_about_us_update (Instagram Feed's Smash Balloon framework)
 * and jetpack_* (the jetpack-connection library bundled with WooCommerce and WooCommerce Services).
 */

defined( 'ABSPATH' ) || exit;

return static function ( callable $remember ) {
	$orphans = array(
		'blc_',                             // Broken Link Checker.
		'puc_cron_check_updates-hostinger', // Hostinger's plugin (the site left Hostinger).
		'siteground_optimizer_',            // SiteGround Optimizer.
		'wp_statistics_',                   // WP Statistics.
		'wpmudev_scheduled_jobs',           // WPMU DEV.
		'wpo_weekly_cron_tasks',            // WP-Optimize.
		'wcct_',                            // Finale: WooCommerce Sales Countdown.
		'jp_purge_transients_cron',         // Jetpack's WAF: Jetpack is installed but inactive,
	);                                      // it schedules it again if it is ever activated.

	$crons   = _get_cron_array();
	$removed = array();
	foreach ( is_array( $crons ) ? $crons : array() as $timestamp => $hooks ) {
		foreach ( $hooks as $hook => $events ) {
			$match = false;
			foreach ( $orphans as $prefix ) {
				if ( 0 === strpos( $hook, $prefix ) ) {
					$match = true;
					break;
				}
			}
			if ( ! $match ) {
				continue;
			}
			foreach ( $events as $event ) {
				$removed[] = array(
					'hook'      => $hook,
					'timestamp' => $timestamp,
					'schedule'  => $event['schedule'],
					'args'      => $event['args'],
				);
			}
		}
	}
	if ( ! $removed ) {
		return; // Idempotent: nothing left to unschedule.
	}

	$remember( 'cron', $removed );
	foreach ( $removed as $event ) {
		wp_unschedule_event( $event['timestamp'], $event['hook'], $event['args'] );
	}
};
