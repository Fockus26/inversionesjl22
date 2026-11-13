<?php
/**
 * WooCommerce › Documentos: the screen that picks the paper size of the PDFs the shop prints
 * (config.php, section 'documents'): the invoice and the shipping report. 80 mm unless it is
 * changed.
 *
 * Only administrators (manage_options). One form posted to admin-post.php; nothing is saved when
 * the value is not in inversionesjl22_site_document_papers(). The configuration it replaces is kept
 * in inversionesjl22_site_config_previous. Works without JavaScript (this screen has none).
 *
 * Where the size is applied: the invoice, through the filters of the PDF plugin's
 * custom/inversionesjl-pdf.php; the shipping report, in shipping-report/pdf.php and its CSS.
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_admin() ) {
	return;
}

const INVERSIONESJL22_SITE_DOCUMENTS_PAGE = 'jl22-documents';

add_action(
	'admin_menu',
	static function () {
		add_submenu_page(
			'woocommerce',
			__( 'Documentos', 'inversionesjl22-site' ),
			__( 'Documentos', 'inversionesjl22-site' ),
			'manage_options',
			INVERSIONESJL22_SITE_DOCUMENTS_PAGE,
			'inversionesjl22_site_documents_screen'
		);
	}
);

add_action(
	'admin_enqueue_scripts',
	static function ( $hook ) {
		if ( 'woocommerce_page_' . INVERSIONESJL22_SITE_DOCUMENTS_PAGE === $hook ) {
			inversionesjl22_site_settings_enqueue_style();
		}
	}
);

/**
 * The paper size a form sent, if it is one of the list; '' otherwise.
 */
function inversionesjl22_site_documents_from_form( string $input ): string {
	$size = sanitize_key( $input );
	return isset( inversionesjl22_site_document_papers()[ $size ] ) ? $size : '';
}

/**
 * The typeface and weight a form sent, each a key of its list or '' (the template's own); null
 * when one is neither, or missing.
 *
 * @param mixed $font
 * @param mixed $weight
 * @return array{font: string, font_weight: string}|null
 */
function inversionesjl22_site_documents_text_from_form( $font, $weight ): ?array {
	if ( ! is_scalar( $font ) || ! is_scalar( $weight ) ) {
		return null;
	}
	$font   = sanitize_key( (string) $font );
	$weight = sanitize_key( (string) $weight );
	if ( ( '' !== $font && ! isset( inversionesjl22_site_document_fonts()[ $font ] ) ) || ( '' !== $weight && ! isset( inversionesjl22_site_document_weights()[ $weight ] ) ) ) {
		return null;
	}
	return array(
		'font'        => $font,
		'font_weight' => $weight,
	);
}

/**
 * Leaves a message for the screen's next load, for this user.
 *
 * @param string[] $messages
 */
function inversionesjl22_site_documents_notice( string $type, array $messages ): void {
	set_transient( 'inversionesjl22_site_documents_notice_' . get_current_user_id(), array( 'type' => $type, 'messages' => $messages ), MINUTE_IN_SECONDS );
}

function inversionesjl22_site_documents_redirect(): void {
	wp_safe_redirect( admin_url( 'admin.php?page=' . INVERSIONESJL22_SITE_DOCUMENTS_PAGE ) );
	exit;
}

/**
 * Every handler of the screen starts here: administrators only, with the action's nonce.
 */
function inversionesjl22_site_documents_guard( string $action ): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'No tienes permiso para cambiar los documentos.', 'inversionesjl22-site' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( $action );
}

/**
 * Saves the configuration with the paper size, keeping the one it replaces.
 */
function inversionesjl22_site_documents_save( array $documents ): void {
	$config              = inversionesjl22_site_config();
	$config['documents'] = $documents;
	update_option( 'inversionesjl22_site_config_previous', get_option( INVERSIONESJL22_SITE_CONFIG_OPTION ), false );
	update_option( INVERSIONESJL22_SITE_CONFIG_OPTION, $config );
}

add_action(
	'admin_post_inversionesjl22_site_save_documents',
	static function () {
		inversionesjl22_site_documents_guard( 'inversionesjl22_site_save_documents' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked above.
		$size = isset( $_POST['paper_size'] ) && is_scalar( $_POST['paper_size'] ) ? inversionesjl22_site_documents_from_form( (string) wp_unslash( $_POST['paper_size'] ) ) : '';
		if ( '' === $size ) {
			inversionesjl22_site_documents_notice( 'error', array( __( 'No se guardó nada: elige uno de los tamaños de la lista.', 'inversionesjl22-site' ) ) );
			inversionesjl22_site_documents_redirect();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked above; compared against the lists in the function.
		$text = inversionesjl22_site_documents_text_from_form( isset( $_POST['font'] ) ? wp_unslash( $_POST['font'] ) : null, isset( $_POST['font_weight'] ) ? wp_unslash( $_POST['font_weight'] ) : null );
		if ( null === $text ) {
			inversionesjl22_site_documents_notice( 'error', array( __( 'No se guardó nada: elige la tipografía y el grosor de las listas.', 'inversionesjl22-site' ) ) );
			inversionesjl22_site_documents_redirect();
		}

		inversionesjl22_site_documents_save( array( 'paper_size' => $size ) + $text );
		/* translators: %s: paper size, e.g. 58 mm */
		inversionesjl22_site_documents_notice( 'success', array( sprintf( __( 'Tamaño del papel guardado: %s.', 'inversionesjl22-site' ), inversionesjl22_site_document_papers()[ $size ]['label'] ) ) );
		inversionesjl22_site_documents_redirect();
	}
);

add_action(
	'admin_post_inversionesjl22_site_reset_documents',
	static function () {
		inversionesjl22_site_documents_guard( 'inversionesjl22_site_reset_documents' );

		// Only this section goes back; with no saved configuration there is nothing to restore.
		if ( is_array( get_option( INVERSIONESJL22_SITE_CONFIG_OPTION ) ) ) {
			inversionesjl22_site_documents_save( inversionesjl22_site_default_config()['documents'] );
		}
		inversionesjl22_site_documents_notice( 'success', array( __( 'Documentos restaurados a los valores iniciales: papel de 80 mm y la tipografía de la plantilla.', 'inversionesjl22-site' ) ) );
		inversionesjl22_site_documents_redirect();
	}
);

function inversionesjl22_site_documents_screen(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$current = inversionesjl22_site_document_paper()['key'];
	$text    = inversionesjl22_site_document_text();
	$default = (string) inversionesjl22_site_default_config()['documents']['paper_size'];
	$post    = admin_url( 'admin-post.php' );
	$notice  = get_transient( 'inversionesjl22_site_documents_notice_' . get_current_user_id() );
	delete_transient( 'inversionesjl22_site_documents_notice_' . get_current_user_id() );
	$details = array(
		'80mm' => __( 'El rollo de siempre. Es el tamaño de fábrica.', 'inversionesjl22-site' ),
		'58mm' => __( 'Rollo angosto, para una impresora de 58 mm. El texto y el QR se achican para caber.', 'inversionesjl22-site' ),
	);
	?>
	<div class="wrap jl-settings">
		<div class="jl-settings__head">
			<div>
				<h1 class="jl-settings__title"><?php esc_html_e( 'Documentos', 'inversionesjl22-site' ); ?></h1>
				<p class="jl-settings__lead"><?php esc_html_e( 'El papel en que se imprimen la factura y el informe de envíos.', 'inversionesjl22-site' ); ?></p>
			</div>
			<div class="jl-settings__actions">
				<form method="post" action="<?php echo esc_url( $post ); ?>">
					<input type="hidden" name="action" value="inversionesjl22_site_reset_documents">
					<?php wp_nonce_field( 'inversionesjl22_site_reset_documents' ); ?>
					<button type="submit" class="jl-button jl-button--outline"><?php esc_html_e( 'Restaurar valores iniciales', 'inversionesjl22-site' ); ?></button>
				</form>
				<button type="submit" form="jl-documents-form" class="jl-button jl-button--primary"><?php esc_html_e( 'Guardar cambios', 'inversionesjl22-site' ); ?></button>
			</div>
		</div>

		<?php // .wp-header-end: where wp-admin moves the other plugins' notices. ?>
		<hr class="wp-header-end">

		<?php inversionesjl22_site_settings_tabs( INVERSIONESJL22_SITE_DOCUMENTS_PAGE ); ?>

		<?php if ( is_array( $notice ) && ! empty( $notice['messages'] ) ) : ?>
			<div class="jl-notice jl-notice--<?php echo esc_attr( 'error' === $notice['type'] ? 'error' : 'success' ); ?>" role="<?php echo esc_attr( 'error' === $notice['type'] ? 'alert' : 'status' ); ?>">
				<?php foreach ( (array) $notice['messages'] as $message ) : ?>
					<p><?php echo esc_html( $message ); ?></p>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<form id="jl-documents-form" class="jl-card jl-card--narrow" method="post" action="<?php echo esc_url( $post ); ?>">
			<input type="hidden" name="action" value="inversionesjl22_site_save_documents">
			<?php wp_nonce_field( 'inversionesjl22_site_save_documents' ); ?>
			<fieldset class="jl-choice">
				<legend><?php esc_html_e( 'Tamaño del papel', 'inversionesjl22-site' ); ?></legend>
				<p class="jl-field__help" id="jl-documents-help"><?php esc_html_e( 'Aplica a la factura de cada pedido y al informe de envíos. La alineación del texto y del QR se ajusta sola al ancho del rollo.', 'inversionesjl22-site' ); ?></p>
				<ul class="jl-options">
					<?php foreach ( inversionesjl22_site_document_papers() as $key => $paper ) : ?>
						<li>
							<label class="jl-option">
								<input type="radio" name="paper_size" value="<?php echo esc_attr( $key ); ?>" aria-describedby="jl-documents-help jl-paper-<?php echo esc_attr( $key ); ?>" <?php checked( $current, $key ); ?>>
								<span>
									<strong><?php echo esc_html( $paper['label'] ); ?></strong>
									<?php echo $key === $default ? esc_html__( '(por defecto)', 'inversionesjl22-site' ) : ''; ?>
									<span class="jl-field__help" id="jl-paper-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $details[ $key ] ?? '' ); ?></span>
								</span>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
			</fieldset>
			<p class="jl-field">
				<label for="jl-documents-font"><?php esc_html_e( 'Tipografía de la factura', 'inversionesjl22-site' ); ?></label>
				<select id="jl-documents-font" name="font" aria-describedby="jl-documents-font-help">
					<option value="" <?php selected( $text['font'], '' ); ?>><?php esc_html_e( 'Según la plantilla', 'inversionesjl22-site' ); ?></option>
					<?php foreach ( inversionesjl22_site_document_fonts() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $text['font'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<span class="jl-field__help" id="jl-documents-font-help"><?php esc_html_e( 'Reemplaza la letra de la factura. «Según la plantilla» deja la que ya trae.', 'inversionesjl22-site' ); ?></span>
			</p>
			<fieldset class="jl-choice">
				<legend><?php esc_html_e( 'Grosor de la tipografía', 'inversionesjl22-site' ); ?></legend>
				<p class="jl-field__help" id="jl-documents-weight-help"><?php esc_html_e( 'Del texto general de la factura; los títulos en negrita de la plantilla se mantienen.', 'inversionesjl22-site' ); ?></p>
				<ul class="jl-options">
					<?php foreach ( array( '' => __( 'Según la plantilla', 'inversionesjl22-site' ) ) + inversionesjl22_site_document_weights() as $key => $label ) : ?>
						<li>
							<label class="jl-option">
								<input type="radio" name="font_weight" value="<?php echo esc_attr( $key ); ?>" aria-describedby="jl-documents-weight-help" <?php checked( $text['font_weight'], $key ); ?>>
								<span><?php echo esc_html( $label ); ?></span>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
			</fieldset>
			<p class="jl-settings__submit">
				<button type="submit" class="jl-button jl-button--primary"><?php esc_html_e( 'Guardar cambios', 'inversionesjl22-site' ); ?></button>
			</p>
		</form>
	</div>
	<?php
}
