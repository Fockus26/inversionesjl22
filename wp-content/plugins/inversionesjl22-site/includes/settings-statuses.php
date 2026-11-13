<?php
/**
 * WooCommerce › Estados y flujo: the screen that edits the order statuses of the configuration
 * (config.php): their order, their names, colours and icons, what their buttons say, which
 * statuses each one can move to, and the shop's own statuses (create, delete).
 *
 * Only administrators (manage_options). The form posts to admin-post.php; nothing is saved when
 * something in it is not valid. The configuration it replaces is kept in
 * inversionesjl22_site_config_previous, so the last save can be undone by hand.
 *
 * Without JavaScript the form still saves everything but the order (assets/js/settings-statuses.js
 * moves the cards).
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_admin() ) {
	return;
}

const INVERSIONESJL22_SITE_STATUSES_PAGE = 'jl22-statuses';

add_action(
	'admin_menu',
	static function () {
		add_submenu_page(
			'woocommerce',
			__( 'Estados y flujo', 'inversionesjl22-site' ),
			__( 'Estados y flujo', 'inversionesjl22-site' ),
			'manage_options',
			INVERSIONESJL22_SITE_STATUSES_PAGE,
			'inversionesjl22_site_statuses_screen'
		);
	}
);

add_action(
	'admin_enqueue_scripts',
	static function ( $hook ) {
		if ( 'woocommerce_page_' . INVERSIONESJL22_SITE_STATUSES_PAGE !== $hook ) {
			return;
		}
		wp_enqueue_style( 'inversionesjl22-admin-settings', INVERSIONESJL22_SITE_URL . 'assets/css/admin-settings.css', array(), inversionesjl22_site_asset_version( 'assets/css/admin-settings.css' ) );
		$tones = '';
		foreach ( inversionesjl22_site_status_palette() as $key => $tone ) {
			$tones .= sprintf( '.jl-settings .jl-tone--%1$s{background:%2$s;color:%3$s;border-color:%4$s}', sanitize_html_class( $key ), $tone['bg'], $tone['fg'], $tone['border'] );
		}
		wp_add_inline_style( 'inversionesjl22-admin-settings', $tones );
		wp_enqueue_script(
			'inversionesjl22-settings-statuses',
			INVERSIONESJL22_SITE_URL . 'assets/js/settings-statuses.js',
			array(),
			inversionesjl22_site_asset_version( 'assets/js/settings-statuses.js' ),
			array( 'in_footer' => true )
		);
		wp_localize_script(
			'inversionesjl22-settings-statuses',
			'inversionesjl22SettingsStatuses',
			array(
				/* translators: 1: status name, 2: its position, 3: how many statuses there are */
				'moved'        => __( '%1$s, posición %2$d de %3$d', 'inversionesjl22-site' ),
				'edit'         => __( 'Editar', 'inversionesjl22-site' ),
				'close'        => __( 'Cerrar', 'inversionesjl22-site' ),
				'confirmReset' => __( '¿Restaurar los estados y el flujo iniciales? Se pierde lo que hayas cambiado en esta pantalla.', 'inversionesjl22-site' ),
			)
		);
	}
);

/**
 * How many orders are in a status.
 */
function inversionesjl22_site_status_order_count( string $slug ): int {
	$counts = wp_count_posts( 'shop_order' );
	return (int) ( $counts->{ 'wc-' . $slug } ?? 0 );
}

/**
 * A status as the screen shows it: its name on its colour, with its icon. Escaped HTML.
 */
function inversionesjl22_site_status_pill( string $slug ): string {
	$status = inversionesjl22_site_config()['statuses'][ $slug ] ?? array();
	$color  = (string) ( $status['color'] ?? '' );
	$icon   = (string) ( $status['icon'] ?? '' );
	return sprintf(
		'<span class="jl-pill%1$s">%2$s%3$s</span>',
		isset( inversionesjl22_site_status_palette()[ $color ] ) ? ' jl-tone--' . esc_attr( sanitize_html_class( $color ) ) : '',
		isset( inversionesjl22_site_status_icons()[ $icon ] ) ? '<span class="dashicons dashicons-' . esc_attr( sanitize_html_class( $icon ) ) . '" aria-hidden="true"></span>' : '',
		esc_html( inversionesjl22_site_status_label( $slug ) )
	);
}

/**
 * The statuses in the order the flow walks them: from each entry, down its first destination
 * before the next one. For the flow's summary, where the lists' order would not read as a path.
 *
 * @return string[]
 */
function inversionesjl22_site_statuses_in_flow_order(): array {
	$next  = inversionesjl22_site_flow_destinations();
	$seen  = array();
	$visit = static function ( string $slug ) use ( &$visit, &$seen, $next ) {
		if ( isset( $seen[ $slug ] ) ) {
			return;
		}
		$seen[ $slug ] = true;
		foreach ( $next[ $slug ] ?? array() as $to ) {
			$visit( $to );
		}
	};
	foreach ( array_keys( $next ) as $slug ) {
		if ( 'entry' === inversionesjl22_site_status_kind( $slug ) ) {
			$visit( $slug );
		}
	}
	foreach ( array_keys( $next ) as $slug ) {
		$visit( $slug );
	}
	return array_keys( $seen );
}

/**
 * The configuration the form asks for, or the reasons it cannot be saved.
 *
 * @param array    $input  $_POST['statuses'], unslashed: slug => fields, in the order wanted.
 * @param string   $new    The name of a status to create, or ''.
 * @param string[] $errors Filled with what is wrong, for the user.
 */
function inversionesjl22_site_statuses_from_form( array $input, string $new, array &$errors ): array {
	$config   = inversionesjl22_site_config();
	$old      = $config['statuses'];
	$text     = static function ( $value ): string {
		$value = sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 40 ) : substr( $value, 0, 40 );
	};
	$statuses = array();
	$next     = array();

	foreach ( $input as $slug => $fields ) {
		$slug = sanitize_key( $slug );
		if ( ! isset( $old[ $slug ] ) || ! is_array( $fields ) || isset( $statuses[ $slug ] ) ) {
			continue;
		}
		$status = $old[ $slug ];
		$core   = ! empty( $status['core'] );

		if ( ! $core && ! empty( $fields['delete'] ) ) {
			$orders = inversionesjl22_site_status_order_count( $slug );
			if ( $orders > 0 ) {
				$errors[] = sprintf(
					/* translators: 1: status name, 2: number of orders */
					_n( '«%1$s» tiene %2$d pedido: muévelo a otro estado antes de eliminarlo.', '«%1$s» tiene %2$d pedidos: muévelos a otro estado antes de eliminarlo.', $orders, 'inversionesjl22-site' ),
					inversionesjl22_site_status_label( $slug ),
					$orders
				);
			} else {
				continue;
			}
		}

		$label = $text( $fields['label'] ?? '' );
		if ( $core ) {
			// Empty, or WooCommerce's own name: not renamed.
			unset( $status['label'] );
			if ( '' !== $label && ( ! empty( $old[ $slug ]['label'] ) || $label !== inversionesjl22_site_status_label( $slug ) ) ) {
				$status['label'] = $label;
			}
		} elseif ( '' !== $label ) {
			$status['label'] = $label;
		}
		// A renamed status gets the default note, written with its new name.
		if ( ( $status['label'] ?? '' ) !== ( $old[ $slug ]['label'] ?? '' ) ) {
			unset( $status['note'] );
			if ( empty( $status['label'] ) && isset( inversionesjl22_site_default_config()['statuses'][ $slug ]['note'] ) ) {
				$status['note'] = inversionesjl22_site_default_config()['statuses'][ $slug ]['note'];
			}
		}

		$action = $text( $fields['action_label'] ?? '' );
		if ( '' !== $action ) {
			$status['action_label'] = $action;
		}
		foreach ( array( 'editable', 'requires_payment' ) as $flag ) {
			if ( ! empty( $fields[ $flag ] ) ) {
				$status[ $flag ] = true;
			} else {
				unset( $status[ $flag ] );
			}
		}

		// Colour and icon: a key of their lists, or none (WooCommerce's own).
		$choices = array(
			'color' => inversionesjl22_site_status_palette(),
			'icon'  => inversionesjl22_site_status_icons(),
		);
		foreach ( $choices as $field => $allowed ) {
			$value = isset( $fields[ $field ] ) && is_scalar( $fields[ $field ] ) ? sanitize_text_field( (string) $fields[ $field ] ) : '';
			if ( isset( $allowed[ $value ] ) ) {
				$status[ $field ] = $value;
			} else {
				unset( $status[ $field ] );
			}
		}

		$statuses[ $slug ] = $status;
		$next[ $slug ]     = array_map( 'sanitize_key', array_filter( (array) ( $fields['next'] ?? array() ), 'is_scalar' ) );
	}

	// A status the form did not send (a stale tab) stays as it was, at the end.
	$kept = array_diff_key( $old, $statuses, array_flip( array_map( 'sanitize_key', array_keys( $input ) ) ) );
	foreach ( $kept as $slug => $status ) {
		$statuses[ $slug ] = $status;
		$next[ $slug ]     = inversionesjl22_site_flow_destinations()[ $slug ] ?? array();
	}

	$new = $text( $new );
	if ( '' !== $new ) {
		$slug = trim( substr( sanitize_title( $new ), 0, 17 ), '-' ); // post_status holds 20 characters, wc- included
		if ( '' === $slug ) {
			$errors[] = __( 'El nombre del nuevo estado necesita al menos una letra o un número.', 'inversionesjl22-site' );
		} elseif ( isset( $old[ $slug ] ) || isset( $statuses[ $slug ] ) || in_array( $slug, inversionesjl22_site_reserved_status_slugs(), true ) ) {
			/* translators: %s: status name */
			$errors[] = sprintf( __( 'Ya existe un estado «%s» (o WooCommerce usa ese nombre). Elige otro.', 'inversionesjl22-site' ), $new );
		} else {
			$statuses[ $slug ] = array(
				'label'        => $new,
				'action_label' => $new,
				'core'         => false,
			);
			$next[ $slug ]     = array();
		}
	}

	// Two statuses with one name cannot be told apart in the lists: neither two of the shop's nor
	// one named after a WooCommerce status the shop does not manage (Pendiente de pago…). The
	// slug check above does not catch it: "Reservado" is the name of the status "reserved".
	$lower = static function ( string $name ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $name ) : strtolower( $name );
	};
	$taken = array();
	foreach ( wc_get_order_statuses() as $key => $name ) {
		$key = 0 === strpos( $key, 'wc-' ) ? substr( $key, 3 ) : $key;
		if ( ! isset( $old[ $key ] ) ) {
			$taken[ $lower( $name ) ] = true;
		}
	}
	foreach ( $statuses as $slug => $status ) {
		// One of WooCommerce's, not renamed: its own name.
		$name = empty( $status['label'] ) ? inversionesjl22_site_core_status_label( (string) $slug ) : $status['label'];
		if ( isset( $taken[ $lower( $name ) ] ) ) {
			/* translators: %s: status name */
			$errors[] = sprintf( __( 'Hay más de un estado llamado «%s». Cada estado necesita un nombre distinto.', 'inversionesjl22-site' ), $name );
		}
		$taken[ $lower( $name ) ] = true;
	}

	// The flow, as the configuration keeps it: destination => origins, both in the statuses' order.
	$flow = array();
	foreach ( array_keys( $statuses ) as $to ) {
		foreach ( array_keys( $statuses ) as $from ) {
			if ( $from !== $to && in_array( $to, $next[ $from ], true ) ) {
				$flow[ $to ][] = $from;
			}
		}
	}

	// The roles' rules forget the statuses that no longer exist.
	$gone = array_diff( array_keys( $old ), array_keys( $statuses ) );
	foreach ( $config['roles'] as $role => $rules ) {
		$rules['statuses'] = array_diff_key( (array) ( $rules['statuses'] ?? array() ), array_flip( $gone ) );
		foreach ( (array) ( $rules['exclude'] ?? array() ) as $context => $excluded ) {
			$rules['exclude'][ $context ] = array_values( array_diff( (array) $excluded, $gone ) );
		}
		$role_flow = array();
		foreach ( array_diff_key( (array) ( $rules['flow'] ?? array() ), array_flip( $gone ) ) as $to => $origins ) {
			$origins = array_values( array_diff( (array) $origins, $gone ) );
			if ( $origins ) {
				$role_flow[ $to ] = $origins;
			}
		}
		$rules['flow']            = $role_flow;
		$config['roles'][ $role ] = $rules;
	}

	$config['statuses'] = $statuses;
	$config['flow']     = $flow;
	return $config;
}

/**
 * Leaves a message for the screen's next load, for this user.
 *
 * @param string[] $messages
 */
function inversionesjl22_site_statuses_notice( string $type, array $messages ): void {
	set_transient( 'inversionesjl22_site_statuses_notice_' . get_current_user_id(), array( 'type' => $type, 'messages' => $messages ), MINUTE_IN_SECONDS );
}

function inversionesjl22_site_statuses_redirect(): void {
	wp_safe_redirect( admin_url( 'admin.php?page=' . INVERSIONESJL22_SITE_STATUSES_PAGE ) );
	exit;
}

add_action(
	'admin_post_inversionesjl22_site_save_statuses',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para cambiar los estados.', 'inversionesjl22-site' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'inversionesjl22_site_save_statuses' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every field is sanitized in inversionesjl22_site_statuses_from_form().
		$input  = isset( $_POST['statuses'] ) && is_array( $_POST['statuses'] ) ? wp_unslash( $_POST['statuses'] ) : array();
		$new    = isset( $_POST['new_status'] ) ? sanitize_text_field( wp_unslash( $_POST['new_status'] ) ) : '';
		$errors = array();
		$config = inversionesjl22_site_statuses_from_form( $input, $new, $errors );

		if ( $errors ) {
			inversionesjl22_site_statuses_notice( 'error', array_merge( array( __( 'No se guardó nada:', 'inversionesjl22-site' ) ), $errors ) );
			inversionesjl22_site_statuses_redirect();
		}

		update_option( 'inversionesjl22_site_config_previous', get_option( INVERSIONESJL22_SITE_CONFIG_OPTION ), false );
		update_option( INVERSIONESJL22_SITE_CONFIG_OPTION, $config );
		inversionesjl22_site_statuses_notice( 'success', array( __( 'Estados y flujo guardados.', 'inversionesjl22-site' ) ) );
		inversionesjl22_site_statuses_redirect();
	}
);

add_action(
	'admin_post_inversionesjl22_site_reset_statuses',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para cambiar los estados.', 'inversionesjl22-site' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'inversionesjl22_site_reset_statuses' );

		// A status created here that still has orders would leave them without a status.
		$defaults = inversionesjl22_site_default_config()['statuses'];
		$blocked  = array();
		foreach ( array_diff_key( inversionesjl22_site_config()['statuses'], $defaults ) as $slug => $status ) {
			if ( inversionesjl22_site_status_order_count( $slug ) > 0 ) {
				$blocked[] = inversionesjl22_site_status_label( $slug );
			}
		}
		if ( $blocked ) {
			inversionesjl22_site_statuses_notice(
				'error',
				array(
					/* translators: %s: status names */
					sprintf( __( 'No se restauró: hay pedidos en %s. Muévelos a otro estado antes.', 'inversionesjl22-site' ), implode( ', ', $blocked ) ),
				)
			);
			inversionesjl22_site_statuses_redirect();
		}

		// Only the statuses and the flow go back: the roles' rules have their own screen.
		$config  = inversionesjl22_site_config();
		$default = inversionesjl22_site_default_config();
		$saved   = get_option( INVERSIONESJL22_SITE_CONFIG_OPTION );
		update_option( 'inversionesjl22_site_config_previous', $saved, false );
		if ( is_array( $saved ) ) {
			$config['statuses'] = $default['statuses'];
			$config['flow']     = $default['flow'];
			update_option( INVERSIONESJL22_SITE_CONFIG_OPTION, $config );
		}
		inversionesjl22_site_statuses_notice( 'success', array( __( 'Estados y flujo restaurados a los valores iniciales.', 'inversionesjl22-site' ) ) );
		inversionesjl22_site_statuses_redirect();
	}
);

function inversionesjl22_site_statuses_screen(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$config  = inversionesjl22_site_config();
	$next    = inversionesjl22_site_flow_destinations();
	$palette = inversionesjl22_site_status_palette();
	$icons   = inversionesjl22_site_status_icons();
	$kinds   = array(
		'entry'    => __( 'Entrada', 'inversionesjl22-site' ),
		'required' => __( 'Obligatorio', 'inversionesjl22-site' ),
		'optional' => __( 'Opcional', 'inversionesjl22-site' ),
		'final'    => __( 'Final', 'inversionesjl22-site' ),
	);
	$notice = get_transient( 'inversionesjl22_site_statuses_notice_' . get_current_user_id() );
	delete_transient( 'inversionesjl22_site_statuses_notice_' . get_current_user_id() );
	?>
	<div class="wrap jl-settings">
		<div class="jl-settings__head">
			<div>
				<h1 class="jl-settings__title"><?php esc_html_e( 'Estados y flujo', 'inversionesjl22-site' ); ?></h1>
				<p class="jl-settings__lead"><?php esc_html_e( 'Los estados de un pedido, su orden y a cuáles puede pasar cada uno.', 'inversionesjl22-site' ); ?></p>
			</div>
			<div class="jl-settings__actions">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-jl-confirm="reset">
					<input type="hidden" name="action" value="inversionesjl22_site_reset_statuses">
					<?php wp_nonce_field( 'inversionesjl22_site_reset_statuses' ); ?>
					<button type="submit" class="jl-button jl-button--outline"><?php esc_html_e( 'Restaurar valores iniciales', 'inversionesjl22-site' ); ?></button>
				</form>
				<button type="submit" form="jl-statuses-form" class="jl-button jl-button--primary"><?php esc_html_e( 'Guardar cambios', 'inversionesjl22-site' ); ?></button>
			</div>
		</div>

		<?php // .wp-header-end: where wp-admin moves the other plugins' notices. ?>
		<hr class="wp-header-end">

		<?php if ( is_array( $notice ) && ! empty( $notice['messages'] ) ) : ?>
			<div class="jl-notice jl-notice--<?php echo esc_attr( 'error' === $notice['type'] ? 'error' : 'success' ); ?>" role="<?php echo esc_attr( 'error' === $notice['type'] ? 'alert' : 'status' ); ?>">
				<?php foreach ( (array) $notice['messages'] as $message ) : ?>
					<p><?php echo esc_html( $message ); ?></p>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="jl-settings__layout">
			<form id="jl-statuses-form" class="jl-settings__main" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="inversionesjl22_site_save_statuses">
				<?php wp_nonce_field( 'inversionesjl22_site_save_statuses' ); ?>

				<p class="jl-settings__hint"><?php esc_html_e( 'El orden es el de las pestañas y las listas de pedidos. Cámbialo con las flechas o arrastrando.', 'inversionesjl22-site' ); ?></p>
				<p class="screen-reader-text" aria-live="polite" data-jl-live></p>

				<ol class="jl-status-list" data-jl-sortable>
					<?php
					foreach ( $config['statuses'] as $slug => $status ) :
						$name   = inversionesjl22_site_status_label( $slug );
						$core   = ! empty( $status['core'] );
						$orders = inversionesjl22_site_status_order_count( $slug );
						$field  = 'statuses[' . $slug . ']';
						$id     = 'jl-status-' . $slug;
						?>
						<li class="jl-status" data-jl-status data-name="<?php echo esc_attr( $name ); ?>">
							<div class="jl-status__row">
								<span class="jl-status__handle" draggable="true" data-jl-handle aria-hidden="true">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" focusable="false"><circle cx="8" cy="6" r="1.8"/><circle cx="16" cy="6" r="1.8"/><circle cx="8" cy="12" r="1.8"/><circle cx="16" cy="12" r="1.8"/><circle cx="8" cy="18" r="1.8"/><circle cx="16" cy="18" r="1.8"/></svg>
								</span>
								<span class="jl-status__moves">
									<?php /* translators: %s: status name */ ?>
									<button type="button" class="jl-icon-button" data-jl-move="up" aria-label="<?php echo esc_attr( sprintf( __( 'Subir %s', 'inversionesjl22-site' ), $name ) ); ?>">
										<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 19V5M6 11l6-6 6 6"/></svg>
									</button>
									<?php /* translators: %s: status name */ ?>
									<button type="button" class="jl-icon-button" data-jl-move="down" aria-label="<?php echo esc_attr( sprintf( __( 'Bajar %s', 'inversionesjl22-site' ), $name ) ); ?>">
										<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 5v14M6 13l6 6 6-6"/></svg>
									</button>
								</span>
								<?php echo inversionesjl22_site_status_pill( (string) $slug ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the function. ?>
								<span class="jl-tag"><?php echo esc_html( $core ? __( 'WooCommerce', 'inversionesjl22-site' ) : __( 'Propio', 'inversionesjl22-site' ) ); ?></span>
								<span class="jl-status__kind"><?php echo esc_html( $kinds[ inversionesjl22_site_status_kind( $slug ) ] ); ?></span>
								<button type="button" class="jl-link-button" data-jl-toggle aria-expanded="true" aria-controls="<?php echo esc_attr( $id ); ?>">
									<span data-jl-toggle-text><?php esc_html_e( 'Cerrar', 'inversionesjl22-site' ); ?></span>
									<span class="screen-reader-text"><?php echo esc_html( $name ); ?></span>
								</button>
							</div>

							<fieldset class="jl-status__next" data-jl-next>
								<legend><?php esc_html_e( 'Puede pasar a', 'inversionesjl22-site' ); ?></legend>
								<div class="jl-chips">
									<?php foreach ( $config['statuses'] as $to => $unused ) : ?>
										<?php if ( $to !== $slug ) : ?>
											<label class="jl-chip">
												<input type="checkbox" name="<?php echo esc_attr( $field ); ?>[next][]" value="<?php echo esc_attr( $to ); ?>" <?php checked( in_array( $to, $next[ $slug ], true ) ); ?>>
												<span><?php echo esc_html( inversionesjl22_site_status_label( $to ) ); ?></span>
											</label>
										<?php endif; ?>
									<?php endforeach; ?>
									<?php if ( ! $next[ $slug ] ) : ?>
										<span class="jl-status__final" data-jl-final><?php esc_html_e( 'Ninguno: es un estado final', 'inversionesjl22-site' ); ?></span>
									<?php endif; ?>
									<button type="button" class="jl-chip-add" data-jl-add aria-expanded="true" hidden><?php esc_html_e( '+ Añadir', 'inversionesjl22-site' ); ?><span class="screen-reader-text"> <?php /* translators: %s: status name */ echo esc_html( sprintf( __( 'un paso desde %s', 'inversionesjl22-site' ), $name ) ); ?></span></button>
								</div>
							</fieldset>

							<div class="jl-status__panel" id="<?php echo esc_attr( $id ); ?>" data-jl-panel>
								<p class="jl-field">
									<label for="<?php echo esc_attr( $id ); ?>-label"><?php esc_html_e( 'Nombre del estado', 'inversionesjl22-site' ); ?></label>
									<input type="text" id="<?php echo esc_attr( $id ); ?>-label" name="<?php echo esc_attr( $field ); ?>[label]" value="<?php echo esc_attr( $name ); ?>" maxlength="40" <?php echo $core ? 'aria-describedby="' . esc_attr( $id ) . '-label-help"' : 'required'; ?>>
									<?php if ( $core ) : ?>
										<span class="jl-field__help" id="<?php echo esc_attr( $id ); ?>-label-help"><?php esc_html_e( 'Es un estado de WooCommerce: déjalo vacío para volver a su nombre original.', 'inversionesjl22-site' ); ?></span>
									<?php endif; ?>
								</p>
								<p class="jl-field">
									<label for="<?php echo esc_attr( $id ); ?>-action"><?php esc_html_e( 'Texto del botón', 'inversionesjl22-site' ); ?></label>
									<input type="text" id="<?php echo esc_attr( $id ); ?>-action" name="<?php echo esc_attr( $field ); ?>[action_label]" value="<?php echo esc_attr( $status['action_label'] ?? $name ); ?>" maxlength="40" required>
								</p>
								<fieldset class="jl-choice">
									<legend><?php esc_html_e( 'Color', 'inversionesjl22-site' ); ?></legend>
									<div class="jl-choice__options">
										<label class="jl-swatch">
											<input type="radio" name="<?php echo esc_attr( $field ); ?>[color]" value="" <?php checked( ! isset( $palette[ $status['color'] ?? '' ] ) ); ?>>
											<span class="jl-swatch__face"><?php esc_html_e( 'El de WooCommerce', 'inversionesjl22-site' ); ?></span>
										</label>
										<?php foreach ( $palette as $key => $tone ) : ?>
											<label class="jl-swatch">
												<input type="radio" name="<?php echo esc_attr( $field ); ?>[color]" value="<?php echo esc_attr( $key ); ?>" <?php checked( ( $status['color'] ?? '' ) === $key ); ?>>
												<span class="jl-swatch__face jl-tone--<?php echo esc_attr( sanitize_html_class( $key ) ); ?>"><?php echo esc_html( $tone['label'] ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
								</fieldset>
								<fieldset class="jl-choice">
									<legend><?php esc_html_e( 'Icono del botón', 'inversionesjl22-site' ); ?></legend>
									<div class="jl-choice__options">
										<label class="jl-swatch">
											<input type="radio" name="<?php echo esc_attr( $field ); ?>[icon]" value="" <?php checked( ! isset( $icons[ $status['icon'] ?? '' ] ) ); ?>>
											<span class="jl-swatch__face"><?php echo esc_html( $core ? __( 'El de WooCommerce', 'inversionesjl22-site' ) : __( 'Ninguno', 'inversionesjl22-site' ) ); ?></span>
										</label>
										<?php foreach ( $icons as $key => $icon ) : ?>
											<label class="jl-swatch jl-swatch--icon" title="<?php echo esc_attr( $icon['label'] ); ?>">
												<input type="radio" name="<?php echo esc_attr( $field ); ?>[icon]" value="<?php echo esc_attr( $key ); ?>" <?php checked( ( $status['icon'] ?? '' ) === $key ); ?>>
												<span class="jl-swatch__face"><span class="dashicons dashicons-<?php echo esc_attr( sanitize_html_class( $key ) ); ?>" aria-hidden="true"></span><span class="screen-reader-text"><?php echo esc_html( $icon['label'] ); ?></span></span>
											</label>
										<?php endforeach; ?>
									</div>
								</fieldset>
								<p class="jl-check">
									<label><input type="checkbox" name="<?php echo esc_attr( $field ); ?>[editable]" value="1" <?php checked( ! empty( $status['editable'] ) ); ?>> <?php esc_html_e( 'Se pueden cambiar los productos del pedido', 'inversionesjl22-site' ); ?></label>
								</p>
								<p class="jl-check">
									<label><input type="checkbox" name="<?php echo esc_attr( $field ); ?>[requires_payment]" value="1" <?php checked( ! empty( $status['requires_payment'] ) ); ?>> <?php esc_html_e( 'Exige el estado de pago para entrar (contra entrega y recogida)', 'inversionesjl22-site' ); ?></label>
								</p>
								<?php if ( ! $core ) : ?>
									<p class="jl-check jl-check--wide">
										<label><input type="checkbox" name="<?php echo esc_attr( $field ); ?>[delete]" value="1" <?php disabled( $orders > 0 ); ?> <?php echo $orders > 0 ? 'aria-describedby="' . esc_attr( $id ) . '-delete-help"' : ''; ?>> <?php esc_html_e( 'Eliminar este estado al guardar', 'inversionesjl22-site' ); ?></label>
										<?php if ( $orders > 0 ) : ?>
											<span class="jl-field__help" id="<?php echo esc_attr( $id ); ?>-delete-help">
												<?php
												/* translators: %d: number of orders */
												echo esc_html( sprintf( _n( '%d pedido está en este estado. Para eliminarlo hay que moverlo antes a otro.', '%d pedidos están en este estado. Para eliminarlo hay que moverlos antes a otro.', $orders, 'inversionesjl22-site' ), $orders ) );
												?>
											</span>
										<?php endif; ?>
									</p>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ol>

				<div class="jl-status jl-status--new">
					<p class="jl-field">
						<label for="jl-new-status"><?php esc_html_e( 'Nuevo estado', 'inversionesjl22-site' ); ?></label>
						<input type="text" id="jl-new-status" name="new_status" maxlength="40" aria-describedby="jl-new-status-help">
						<span class="jl-field__help" id="jl-new-status-help"><?php esc_html_e( 'Escribe su nombre y guarda: se añade al final. Después podrás ordenarlo y decidir desde qué estados se llega a él. Ningún rol lo ve hasta que se le dé permiso.', 'inversionesjl22-site' ); ?></span>
					</p>
				</div>

				<p class="jl-settings__submit">
					<button type="submit" class="jl-button jl-button--primary"><?php esc_html_e( 'Guardar cambios', 'inversionesjl22-site' ); ?></button>
				</p>
			</form>

			<aside class="jl-settings__aside" aria-labelledby="jl-flow-title">
				<h2 id="jl-flow-title"><?php esc_html_e( 'Vista del flujo', 'inversionesjl22-site' ); ?></h2>
				<p class="jl-settings__hint"><?php esc_html_e( 'Lo que está guardado. Se actualiza al guardar.', 'inversionesjl22-site' ); ?></p>
				<ol class="jl-flow">
					<?php foreach ( inversionesjl22_site_statuses_in_flow_order() as $slug ) : ?>
						<li>
							<span class="jl-flow__step">
								<?php echo inversionesjl22_site_status_pill( (string) $slug ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the function. ?>
								<span class="jl-status__kind"><?php echo esc_html( $kinds[ inversionesjl22_site_status_kind( $slug ) ] ); ?></span>
							</span>
							<?php if ( $next[ $slug ] ) : ?>
								<span class="jl-flow__next">
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
									<?php echo esc_html( implode( ', ', array_map( 'inversionesjl22_site_status_label', $next[ $slug ] ) ) ); ?>
								</span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ol>
				<p class="jl-settings__note"><?php echo wp_kses( __( '<b>Opcional</b> quiere decir que un pedido puede llegar al final sin pasar por ese estado.', 'inversionesjl22-site' ), array( 'b' => array() ) ); ?></p>
			</aside>
		</div>
	</div>
	<?php
}
