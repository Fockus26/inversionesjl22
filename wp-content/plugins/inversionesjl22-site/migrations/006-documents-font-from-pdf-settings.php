<?php
/**
 * The invoice's typeface and weight moved from the PDF Invoices settings (General › Display
 * Settings: "Tipografía", "Grosor de la tipografía") to WooCommerce › Documentos. If the site had
 * them set, they are copied into the plugin's configuration so the invoice prints the same.
 *
 * Only when they are valid keys of the lists the screen offers, and only when the configuration
 * has not saved its own yet. Idempotent: a second run finds them already saved.
 */

defined( 'ABSPATH' ) || exit;

return static function ( callable $remember ) {
	$legacy = get_option( 'wpo_wcpdf_settings_general' );
	if ( ! is_array( $legacy ) ) {
		return;
	}
	$saved = get_option( INVERSIONESJL22_SITE_CONFIG_OPTION );
	if ( is_array( $saved ) && is_array( $saved['documents'] ?? null ) && ( isset( $saved['documents']['font'] ) || isset( $saved['documents']['font_weight'] ) ) ) {
		return;
	}

	$copy = array();
	$font = (string) ( $legacy['jl_template_font'] ?? '' );
	if ( isset( inversionesjl22_site_document_fonts()[ $font ] ) ) {
		$copy['font'] = $font;
	}
	$weight = (string) ( $legacy['jl_template_font_weight'] ?? '' );
	if ( isset( inversionesjl22_site_document_weights()[ $weight ] ) ) {
		$copy['font_weight'] = $weight;
	}
	if ( ! $copy ) {
		return;
	}

	$remember( 'option:' . INVERSIONESJL22_SITE_CONFIG_OPTION, $saved );
	$config              = inversionesjl22_site_config();
	$config['documents'] = $copy + $config['documents'];
	update_option( INVERSIONESJL22_SITE_CONFIG_OPTION, $config );
};
