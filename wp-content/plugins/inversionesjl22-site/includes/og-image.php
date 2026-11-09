<?php
/**
 * Open Graph image for the front page (and the posts page): assets/images/Preview.png, so links
 * to the store shared on WhatsApp, Facebook and the like show a preview.
 *
 * Moved from the child theme (woodmart-child/inc/integrations/open-graph.php), which printed
 * og:title, og:description, og:image, og:url and og:type itself. Yoast already prints the title,
 * url and type, so only the image is added, through Yoast and not as a second set of tags. The
 * child theme is not active in production, so there is no og:image there until now.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wpseo_add_opengraph_images',
	static function ( $image_container ) {
		if ( ! ( is_front_page() || is_home() ) ) {
			return;
		}
		// If the child theme is ever active again, its own tags already carry the image.
		if ( has_action( 'wp_head', 'agregar_meta_og_tags' ) ) {
			return;
		}
		$image_container->add_image(
			array(
				'url'    => INVERSIONESJL22_SITE_URL . 'assets/images/Preview.png',
				'width'  => 1280,
				'height' => 640,
				'type'   => 'image/png',
			)
		);
	}
);
