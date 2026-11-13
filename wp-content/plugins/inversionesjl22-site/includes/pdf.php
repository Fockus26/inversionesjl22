<?php
/**
 * The plugin's PDF engine: its own copy of Dompdf (vendor/, namespace InversionesJL22Site\Vendor,
 * see vendor/autoload.php), so the shop's PDFs do not depend on PDF Invoices & Packing Slips.
 * The copy is the same Dompdf 3.1.6 that plugin bundles, under another name: both load side by
 * side while PDF Invoices stays active, and the PDFs come out as they did.
 *
 * Used by the shipping report (shipping-report/pdf.php). Dompdf writes only to
 * uploads/inversionesjl22-site/ (its temp files and the fonts' metric caches); the fonts it reads
 * are in the plugin: Dompdf's own (vendor/dompdf/dompdf/lib/fonts) and assets/fonts/ (Open Sans,
 * Roboto Slab, Segoe and the currency symbols, from PDF Invoices; listed in its
 * installed-fonts.json, which Dompdf only reads).
 */

defined( 'ABSPATH' ) || exit;

/**
 * A writable folder for Dompdf under uploads/inversionesjl22-site/ (or the system's temp folder
 * when uploads cannot be written), created on first use with an index.php so it is not listed.
 */
function inversionesjl22_site_pdf_work_dir( string $sub ): string {
	$uploads = wp_upload_dir( null, false );
	$dir     = empty( $uploads['error'] ) ? trailingslashit( $uploads['basedir'] ) . 'inversionesjl22-site/' . $sub : '';
	if ( '' === $dir || ! ( is_dir( $dir ) || wp_mkdir_p( $dir ) ) || ! wp_is_writable( $dir ) ) {
		$dir = untrailingslashit( get_temp_dir() ) . '/inversionesjl22-site-' . $sub;
		wp_mkdir_p( $dir );
	}
	if ( ! file_exists( $dir . '/index.php' ) ) {
		@file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- a missing index.php only allows a listing
	}
	return $dir;
}

/**
 * A new Dompdf with the plugin's folders. $options are Dompdf's (Options::set() keys); the rest
 * keep Dompdf's defaults, which is what the shipping report always used.
 *
 * @param array $options
 * @return \InversionesJL22Site\Vendor\Dompdf\Dompdf
 */
function inversionesjl22_site_dompdf( array $options = array() ) {
	require_once INVERSIONESJL22_SITE_DIR . 'vendor/autoload.php';

	$temp     = inversionesjl22_site_pdf_work_dir( 'dompdf' );
	$defaults = array(
		'tempDir'       => $temp,
		'logOutputFile' => $temp . '/log.htm',
		'fontDir'       => INVERSIONESJL22_SITE_DIR . 'assets/fonts',
		'fontCache'     => inversionesjl22_site_pdf_work_dir( 'fonts' ),
	);
	return new \InversionesJL22Site\Vendor\Dompdf\Dompdf( new \InversionesJL22Site\Vendor\Dompdf\Options( $options + $defaults ) );
}
