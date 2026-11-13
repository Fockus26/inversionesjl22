<?php
/**
 * Autoloader of the plugin's own Dompdf (includes/pdf.php loads it only when a PDF is made).
 *
 * Where it comes from: Dompdf 3.1.6 with php-font-lib, php-svg-lib, masterminds/html5 and
 * sabberworm/php-css-parser, the copy PDF Invoices & Packing Slips 5.16.6 ships in
 * vendor/strauss/ (the version the shop's PDFs were made with, PHP 7.1+). Strauss prefixed it
 * WPO\IPS\Vendor\; here the prefix is InversionesJL22Site\Vendor\, so both copies load side by
 * side while PDF Invoices stays active. Left out: READMEs and tests, the DejaVu italic/oblique
 * files (no document of the shop uses italics: installed-fonts.dist.json points those variants to
 * the upright files) and the metric caches (*.ufm.json, written at run time to uploads/).
 * To update it: copy a newer strauss build and replace the prefix (context/PHASE_LOG/feat-pdf-propio.md).
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( $class ) {
		static $map = null;
		if ( null === $map ) {
			$dir = __DIR__;
			$map = array(
				'InversionesJL22Site\\Vendor\\Dompdf\\Cpdf' => $dir . '/dompdf/dompdf/lib/Cpdf.php',
				'InversionesJL22Site\\Vendor\\Dompdf\\'     => $dir . '/dompdf/dompdf/src/',
				'InversionesJL22Site\\Vendor\\FontLib\\'    => $dir . '/dompdf/php-font-lib/src/FontLib/',
				'InversionesJL22Site\\Vendor\\Svg\\'        => $dir . '/dompdf/php-svg-lib/src/Svg/',
				'InversionesJL22Site\\Vendor\\Masterminds\\' => $dir . '/masterminds/html5/src/',
				'InversionesJL22Site\\Vendor\\Sabberworm\\CSS\\' => $dir . '/sabberworm/php-css-parser/src/',
			);
		}
		if ( 0 !== strpos( $class, 'InversionesJL22Site\\Vendor\\' ) ) {
			return;
		}
		foreach ( $map as $prefix => $path ) {
			if ( '\\' !== substr( $prefix, -1 ) ) {
				if ( $class === $prefix ) {
					require_once $path;
					return;
				}
				continue;
			}
			if ( 0 === strpos( $class, $prefix ) ) {
				$file = $path . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
				if ( is_readable( $file ) ) {
					require_once $file;
				}
				return;
			}
		}
	}
);
