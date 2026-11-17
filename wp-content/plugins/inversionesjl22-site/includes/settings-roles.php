<?php
/**
 * Inversiones JL22 › Roles y permisos: the screen that edits what each restricted role sees and does
 * (config.php, section 'roles'): the orders it sees per status, where it can move them from, its
 * own steps on top of the flow, the wp-admin screens of its menu and whether it scans the
 * invoice's QR (qr-scan.php). It also creates roles (a
 * WordPress role with the capabilities of an existing restricted one) and deletes the ones it
 * created.
 *
 * Only administrators (manage_options), who are never restricted and are not listed. One role
 * per view (&role=<slug>), one form per role, posted to admin-post.php; nothing is saved when
 * something in it is not valid. The configuration it replaces is kept in
 * inversionesjl22_site_config_previous. Everything saves without JavaScript
 * (assets/js/settings-roles.js only unlocks a row when "Ver los pedidos" is ticked, and asks
 * before restoring or deleting).
 *
 * Not edited here, kept as they are: each role's 'screens' and 'admin_bar', and the entries of
 * 'exclude' that are not statuses, except 'combine_orders' (or the old 'wuoc_combine') in
 * 'bulk_actions', which is the "Puede combinar pedidos" box (combined-orders.php).
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_admin() ) {
	return;
}

const INVERSIONESJL22_SITE_ROLES_PAGE = 'jl22-roles';

// Its menu entry: admin-menu.php (Inversiones JL22 › Roles y permisos).

add_action(
	'admin_enqueue_scripts',
	static function ( $hook ) {
		if ( ! inversionesjl22_site_is_plugin_screen( (string) $hook, INVERSIONESJL22_SITE_ROLES_PAGE ) ) {
			return;
		}
		inversionesjl22_site_settings_enqueue_style();
		wp_enqueue_script(
			'inversionesjl22-settings-roles',
			INVERSIONESJL22_SITE_URL . 'assets/js/settings-roles.js',
			array(),
			inversionesjl22_site_asset_version( 'assets/js/settings-roles.js' ),
			array( 'in_footer' => true )
		);
		wp_localize_script(
			'inversionesjl22-settings-roles',
			'inversionesjl22SettingsRoles',
			array(
				/* translators: 1: how many screens of the menu are ticked, 2: how many it has */
				'count'   => __( '%1$d de %2$d marcadas', 'inversionesjl22-site' ),
				/* translators: %d: how many screens match the search */
				'results' => __( '%d pantallas coinciden con la búsqueda.', 'inversionesjl22-site' ),
				'none'    => __( 'Ninguna pantalla coincide con la búsqueda.', 'inversionesjl22-site' ),
			)
		);
	}
);

/**
 * The matrix's columns after "Ver los pedidos": context of 'exclude' => its group ('in': what
 * the role does with the orders in the status; 'to': where it can move orders to the status
 * from) and its heading. A ticked box is a status NOT in that context's list.
 *
 * @return array<string, array{group: string, label: string}>
 */
function inversionesjl22_site_role_contexts(): array {
	return array(
		'order_view'            => array( 'group' => 'in', 'label' => __( 'Abrir el pedido', 'inversionesjl22-site' ) ),
		'qr_actions'            => array( 'group' => 'in', 'label' => __( 'Factura PDF', 'inversionesjl22-site' ) ),
		'quick_actions'         => array( 'group' => 'to', 'label' => __( 'Acciones rápidas', 'inversionesjl22-site' ) ),
		'bulk_actions'          => array( 'group' => 'to', 'label' => __( 'En lote', 'inversionesjl22-site' ) ),
		'order_preview_actions' => array( 'group' => 'to', 'label' => __( 'Vista previa', 'inversionesjl22-site' ) ),
		'order_view_actions'    => array( 'group' => 'to', 'label' => __( 'Selector del pedido', 'inversionesjl22-site' ) ),
	);
}

/**
 * The roles the plugin ships with: never deleted, and the ones "restore" takes back.
 *
 * @return string[]
 */
function inversionesjl22_site_fixed_roles(): array {
	return array_keys( inversionesjl22_site_default_config()['roles'] );
}

/**
 * The roles this screen edits, slug => rules. Never the administrator.
 */
function inversionesjl22_site_editable_roles(): array {
	$roles = inversionesjl22_site_config()['roles'];
	unset( $roles['administrator'] );
	return array_filter( $roles, 'is_array' );
}

function inversionesjl22_site_role_name( string $role ): string {
	$names = wp_roles()->role_names;
	return isset( $names[ $role ] ) ? translate_user_role( $names[ $role ] ) : $role;
}

/**
 * The entries of wp-admin's menu, as the keys roles-menu.php compares: key => its name and the
 * top-level entry it hangs from. Only where the menu is built (a screen, not admin-post.php).
 *
 * @return array<string, array{label: string, group: string}>
 */
function inversionesjl22_site_roles_menu_options(): array {
	global $menu, $submenu;
	$name    = static function ( $title ): string {
		// Without the counters ("Pedidos <span>12</span>").
		return trim( wp_strip_all_tags( (string) preg_replace( '/<span.*<\/span>/s', '', (string) $title ) ) );
	};
	$options = array();
	foreach ( (array) $menu as $item ) {
		$slug = (string) ( $item[2] ?? '' );
		$top  = $name( $item[0] ?? '' );
		if ( '' === $slug || '' === $top || 0 === strpos( $slug, 'separator' ) ) {
			continue;
		}
		if ( empty( $submenu[ $slug ] ) ) {
			$options += array( inversionesjl22_site_menu_link( $slug ) => array( 'label' => $top, 'group' => $top ) );
			continue;
		}
		foreach ( $submenu[ $slug ] as $child ) {
			$label = $name( $child[0] ?? '' );
			if ( '' !== $label && ! empty( $child[2] ) ) {
				$options += array( inversionesjl22_site_menu_link( (string) $child[2], $slug ) => array( 'label' => $label, 'group' => $top ) );
			}
		}
	}
	return $options;
}

/**
 * The default rules of a role, without what the shop no longer has (a status deleted in Estados
 * y flujo).
 */
function inversionesjl22_site_default_role_rules( string $role ): array {
	$defaults = inversionesjl22_site_default_config();
	$rules    = $defaults['roles'][ $role ];
	$exists   = inversionesjl22_site_config()['statuses'];

	$rules['statuses'] = array_intersect_key( $rules['statuses'], $exists );
	foreach ( $rules['exclude'] as $context => $excluded ) {
		$rules['exclude'][ $context ] = array_values(
			array_filter(
				$excluded,
				static function ( $slug ) use ( $exists, $defaults ) {
					return isset( $exists[ $slug ] ) || ! isset( $defaults['statuses'][ $slug ] );
				}
			)
		);
	}
	$flow = array();
	foreach ( array_intersect_key( $rules['flow'], $exists ) as $to => $origins ) {
		$origins = array_values( array_intersect( (array) $origins, array_keys( $exists ) ) );
		if ( $origins ) {
			$flow[ $to ] = $origins;
		}
	}
	$rules['flow'] = $flow;
	return $rules;
}

/**
 * The rules the form asks for a role, or the reasons they cannot be saved.
 *
 * @param string        $role   A role of the configuration.
 * @param array         $input  The form, unslashed: perm, flow_remove, flow_from, flow_to, menu,
 *                              qr_scan, combine.
 * @param string[]|null $menus  The menu keys the screen offered; null when it is not known.
 * @param string[]      $errors Filled with what is wrong, for the user.
 */
function inversionesjl22_site_role_rules_from_form( string $role, array $input, ?array $menus, array &$errors ): array {
	$config = inversionesjl22_site_config();
	$rules  = $config['roles'][ $role ];
	$slugs  = array_map( 'strval', array_keys( $config['statuses'] ) );
	$perm   = isset( $input['perm'] ) && is_array( $input['perm'] ) ? $input['perm'] : array();
	$ticked = static function ( string $slug, string $box ) use ( $perm ): bool {
		return isset( $perm[ $slug ] ) && is_array( $perm[ $slug ] ) && ! empty( $perm[ $slug ][ $box ] );
	};

	// Ver los pedidos. The keys keep the order they had.
	$statuses = array();
	foreach ( array_unique( array_merge( array_map( 'strval', array_keys( (array) ( $rules['statuses'] ?? array() ) ) ), $slugs ) ) as $slug ) {
		if ( in_array( $slug, $slugs, true ) ) {
			$statuses[ $slug ] = $ticked( $slug, 'see' );
		}
	}

	// The rest of the row. A row that was locked (the role did not see the status) sends no
	// boxes: its lists stay as they were. Each list keeps its order, and what is not a status.
	$exclude = (array) ( $rules['exclude'] ?? array() );
	foreach ( array_keys( inversionesjl22_site_role_contexts() ) as $context ) {
		$old  = array_values( (array) ( $exclude[ $context ] ?? array() ) );
		$out  = array();
		$want = array();
		foreach ( $slugs as $slug ) {
			$want[ $slug ] = $statuses[ $slug ] && $ticked( $slug, 'row' ) ? ! $ticked( $slug, $context ) : in_array( $slug, $old, true );
		}
		foreach ( $old as $entry ) {
			if ( ! is_string( $entry ) || ( isset( $want[ $entry ] ) && ! $want[ $entry ] ) || in_array( $entry, $out, true ) ) {
				continue;
			}
			$out[] = $entry;
		}
		foreach ( $slugs as $slug ) {
			if ( $want[ $slug ] && ! in_array( $slug, $out, true ) ) {
				$out[] = $slug;
			}
		}
		$exclude[ $context ] = $out;
	}

	// Combinar pedidos: an entry of its bulk actions that is not a status (the old key goes).
	$bulk = array_values( array_diff( (array) ( $exclude['bulk_actions'] ?? array() ), array( INVERSIONESJL22_SITE_COMBINE_ACTION, INVERSIONESJL22_SITE_COMBINE_ACTION_OLD ) ) );
	if ( empty( $input['combine'] ) ) {
		$bulk[] = INVERSIONESJL22_SITE_COMBINE_ACTION;
	}
	$exclude['bulk_actions'] = $bulk;

	// Its own steps: the ones it had, minus the ones ticked to remove, plus the new one.
	$remove = array_filter( (array) ( $input['flow_remove'] ?? array() ), 'is_string' );
	$flow   = array();
	foreach ( (array) ( $rules['flow'] ?? array() ) as $to => $origins ) {
		foreach ( (array) $origins as $from ) {
			if ( in_array( (string) $to, $slugs, true ) && in_array( $from, $slugs, true ) && ! in_array( $from . ':' . $to, $remove, true ) ) {
				$flow[ $to ][] = $from;
			}
		}
	}
	$from = isset( $input['flow_from'] ) && is_string( $input['flow_from'] ) ? $input['flow_from'] : '';
	$to   = isset( $input['flow_to'] ) && is_string( $input['flow_to'] ) ? $input['flow_to'] : '';
	if ( '' !== $from || '' !== $to ) {
		if ( ! in_array( $from, $slugs, true ) || ! in_array( $to, $slugs, true ) ) {
			$errors[] = __( 'Para añadir un paso elige los dos estados: de cuál sale y a cuál llega.', 'inversionesjl22-site' );
		} elseif ( $from === $to ) {
			$errors[] = __( 'Un paso necesita dos estados distintos.', 'inversionesjl22-site' );
		} elseif ( in_array( $from, (array) ( $config['flow'][ $to ] ?? array() ), true ) ) {
			/* translators: 1: origin status, 2: destination status */
			$errors[] = sprintf( __( 'El paso de %1$s a %2$s ya está en el flujo general: no hace falta añadirlo al rol.', 'inversionesjl22-site' ), inversionesjl22_site_status_label( $from ), inversionesjl22_site_status_label( $to ) );
		} elseif ( in_array( $from, $flow[ $to ] ?? array(), true ) ) {
			/* translators: 1: origin status, 2: destination status */
			$errors[] = sprintf( __( 'El rol ya tiene el paso de %1$s a %2$s.', 'inversionesjl22-site' ), inversionesjl22_site_status_label( $from ), inversionesjl22_site_status_label( $to ) );
		} else {
			$flow[ $to ][] = $from;
		}
	}

	// The menu: only keys the screen offered or the role already had, in the order it had them.
	// The dashboard always: it is where a screen off the list sends the role.
	$old    = array_values( array_filter( (array) ( $rules['menu'] ?? array() ), 'is_string' ) );
	$asked  = array_filter( (array) ( $input['menu'] ?? array() ), 'is_string' );
	$menu   = array_values( array_intersect( $old, $asked ) );
	$added  = array_diff( $asked, $old );
	$stale  = false;
	foreach ( null === $menus ? array() : $menus as $key ) {
		if ( in_array( $key, $added, true ) && ! in_array( $key, $menu, true ) ) {
			$menu[] = $key;
		}
	}
	foreach ( $added as $key ) {
		$stale = $stale || ( null === $menus && 'index.php' !== $key );
	}
	if ( $stale ) {
		$errors[] = __( 'La lista de pantallas del menú caducó. Vuelve a abrir Roles y permisos y marca las pantallas de nuevo.', 'inversionesjl22-site' );
	}
	if ( ! in_array( 'index.php', $menu, true ) ) {
		array_unshift( $menu, 'index.php' );
	}

	$rules['statuses'] = $statuses;
	$rules['exclude']  = $exclude;
	$rules['flow']     = $flow;
	$rules['menu']     = $menu;
	$rules['qr_scan']  = ! empty( $input['qr_scan'] );
	return $rules;
}

/**
 * Leaves a message for the screen's next load, for this user.
 *
 * @param string[] $messages
 */
function inversionesjl22_site_roles_notice( string $type, array $messages ): void {
	set_transient( 'inversionesjl22_site_roles_notice_' . get_current_user_id(), array( 'type' => $type, 'messages' => $messages ), MINUTE_IN_SECONDS );
}

/**
 * Back to the screen: a role's view, the new role form ('+') or the first role ('').
 */
function inversionesjl22_site_roles_redirect( string $role = '' ): void {
	$url = admin_url( 'admin.php?page=' . INVERSIONESJL22_SITE_ROLES_PAGE );
	if ( '+' === $role ) {
		$url = add_query_arg( 'new', '1', $url );
	} elseif ( '' !== $role ) {
		$url = add_query_arg( 'role', $role, $url );
	}
	wp_safe_redirect( $url );
	exit;
}

function inversionesjl22_site_roles_save_config( array $config ): void {
	update_option( 'inversionesjl22_site_config_previous', get_option( INVERSIONESJL22_SITE_CONFIG_OPTION ), false );
	update_option( INVERSIONESJL22_SITE_CONFIG_OPTION, $config );
}

/**
 * Every handler of the screen starts here: administrators only, with the action's nonce.
 */
function inversionesjl22_site_roles_guard( string $action ): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'No tienes permiso para cambiar los roles.', 'inversionesjl22-site' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( $action );
}

/**
 * The role a handler was asked for, if it is one this screen edits; '' otherwise.
 */
function inversionesjl22_site_roles_posted_role(): string {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the handlers check it first (inversionesjl22_site_roles_guard()).
	$role = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : '';
	return isset( inversionesjl22_site_editable_roles()[ $role ] ) ? $role : '';
}

add_action(
	'admin_post_inversionesjl22_site_save_role',
	static function () {
		inversionesjl22_site_roles_guard( 'inversionesjl22_site_save_role' );
		$role = inversionesjl22_site_roles_posted_role();
		if ( '' === $role ) {
			inversionesjl22_site_roles_notice( 'error', array( __( 'Ese rol ya no existe. No se guardó nada.', 'inversionesjl22-site' ) ) );
			inversionesjl22_site_roles_redirect();
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked above; every value is compared against the configuration's own lists in inversionesjl22_site_role_rules_from_form().
		$input = array();
		foreach ( array( 'perm', 'flow_remove', 'menu' ) as $field ) {
			$input[ $field ] = isset( $_POST[ $field ] ) && is_array( $_POST[ $field ] ) ? wp_unslash( $_POST[ $field ] ) : array();
		}
		foreach ( array( 'flow_from', 'flow_to' ) as $field ) {
			$input[ $field ] = isset( $_POST[ $field ] ) && is_scalar( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		}
		$input['qr_scan'] = isset( $_POST['qr_scan'] ) && '1' === $_POST['qr_scan'];
		$input['combine'] = isset( $_POST['combine'] ) && '1' === $_POST['combine'];
		// phpcs:enable
		$menus  = get_transient( 'inversionesjl22_site_roles_menu_' . get_current_user_id() );
		$errors = array();
		$rules  = inversionesjl22_site_role_rules_from_form( $role, $input, is_array( $menus ) ? $menus : null, $errors );

		if ( $errors ) {
			inversionesjl22_site_roles_notice( 'error', array_merge( array( __( 'No se guardó nada:', 'inversionesjl22-site' ) ), $errors ) );
			inversionesjl22_site_roles_redirect( $role );
		}

		$config                   = inversionesjl22_site_config();
		$config['roles'][ $role ] = $rules;
		inversionesjl22_site_roles_save_config( $config );
		/* translators: %s: role name */
		inversionesjl22_site_roles_notice( 'success', array( sprintf( __( 'Permisos de %s guardados.', 'inversionesjl22-site' ), inversionesjl22_site_role_name( $role ) ) ) );
		inversionesjl22_site_roles_redirect( $role );
	}
);

add_action(
	'admin_post_inversionesjl22_site_create_role',
	static function () {
		inversionesjl22_site_roles_guard( 'inversionesjl22_site_create_role' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked above.
		$name = isset( $_POST['role_name'] ) ? sanitize_text_field( wp_unslash( $_POST['role_name'] ) ) : '';
		$base = isset( $_POST['role_base'] ) ? sanitize_key( wp_unslash( $_POST['role_base'] ) ) : '';
		// phpcs:enable
		$name     = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 40 ) : substr( $name, 0, 40 );
		$slug     = trim( sanitize_key( str_replace( '-', '_', sanitize_title( $name ) ) ), '_' );
		$config   = inversionesjl22_site_config();
		$roles    = inversionesjl22_site_editable_roles();
		$reserved = array( 'administrator', 'shop_manager', 'editor', 'author', 'contributor', 'subscriber', 'customer' );
		$source   = isset( $roles[ $base ] ) ? get_role( $base ) : null;
		$errors   = array();

		if ( '' === $slug ) {
			$errors[] = __( 'El nombre del rol necesita al menos una letra o un número.', 'inversionesjl22-site' );
		} elseif ( in_array( $slug, $reserved, true ) || isset( $config['roles'][ $slug ] ) || wp_roles()->is_role( $slug ) ) {
			/* translators: %s: role name */
			$errors[] = sprintf( __( 'Ya existe un rol «%s» (o WordPress usa ese nombre). Elige otro.', 'inversionesjl22-site' ), $name );
		}
		if ( ! $source ) {
			$errors[] = __( 'Elige de qué rol se copian las capacidades.', 'inversionesjl22-site' );
		}
		if ( ! $errors && ! add_role( $slug, $name, $source->capabilities ) ) {
			$errors[] = __( 'WordPress no pudo crear el rol.', 'inversionesjl22-site' );
		}
		if ( $errors ) {
			inversionesjl22_site_roles_notice( 'error', array_merge( array( __( 'No se creó el rol:', 'inversionesjl22-site' ) ), $errors ) );
			inversionesjl22_site_roles_redirect( '+' );
		}

		// It sees no status and only the dashboard, until someone ticks more. The screens it
		// can open without a menu entry (an order) and its admin bar: the ones of the role it
		// copies, which this screen does not edit.
		$config['roles'][ $slug ] = array(
			'statuses'  => array_fill_keys( array_keys( $config['statuses'] ), false ),
			'exclude'   => array_fill_keys( array_keys( inversionesjl22_site_role_contexts() ), array() ),
			'flow'      => array(),
			'menu'      => array( 'index.php' ),
			'screens'   => array_values( (array) ( $roles[ $base ]['screens'] ?? array() ) ),
			'admin_bar' => array_values( (array) ( $roles[ $base ]['admin_bar'] ?? array() ) ),
			'qr_scan'   => false,
			'custom'    => true,
		);
		inversionesjl22_site_roles_save_config( $config );
		/* translators: %s: role name */
		inversionesjl22_site_roles_notice( 'success', array( sprintf( __( 'Rol %s creado. Todavía no ve ningún pedido: marca lo que le corresponde y guarda.', 'inversionesjl22-site' ), $name ) ) );
		inversionesjl22_site_roles_redirect( $slug );
	}
);

add_action(
	'admin_post_inversionesjl22_site_delete_role',
	static function () {
		inversionesjl22_site_roles_guard( 'inversionesjl22_site_delete_role' );
		$role   = inversionesjl22_site_roles_posted_role();
		$config = inversionesjl22_site_config();

		if ( '' === $role || in_array( $role, inversionesjl22_site_fixed_roles(), true ) || empty( $config['roles'][ $role ]['custom'] ) ) {
			inversionesjl22_site_roles_notice( 'error', array( __( 'Ese rol no se puede eliminar.', 'inversionesjl22-site' ) ) );
			inversionesjl22_site_roles_redirect( $role );
		}
		$name = inversionesjl22_site_role_name( $role );
		if ( get_users( array( 'role' => $role, 'number' => 1, 'fields' => 'ID' ) ) ) {
			/* translators: %s: role name */
			inversionesjl22_site_roles_notice( 'error', array( sprintf( __( 'No se eliminó %s: hay usuarios con ese rol. Cámbiales el rol en Usuarios antes.', 'inversionesjl22-site' ), $name ) ) );
			inversionesjl22_site_roles_redirect( $role );
		}

		remove_role( $role );
		unset( $config['roles'][ $role ] );
		inversionesjl22_site_roles_save_config( $config );
		/* translators: %s: role name */
		inversionesjl22_site_roles_notice( 'success', array( sprintf( __( 'Rol %s eliminado.', 'inversionesjl22-site' ), $name ) ) );
		inversionesjl22_site_roles_redirect();
	}
);

add_action(
	'admin_post_inversionesjl22_site_reset_roles',
	static function () {
		inversionesjl22_site_roles_guard( 'inversionesjl22_site_reset_roles' );

		// Only the plugin's own roles go back: the statuses and the flow have their screen, and
		// the roles created here stay.
		$saved = get_option( INVERSIONESJL22_SITE_CONFIG_OPTION );
		if ( is_array( $saved ) ) {
			$config = inversionesjl22_site_config();
			foreach ( inversionesjl22_site_fixed_roles() as $role ) {
				$config['roles'][ $role ] = inversionesjl22_site_default_role_rules( $role );
			}
			inversionesjl22_site_roles_save_config( $config );
		}
		$names = array_map( 'inversionesjl22_site_role_name', inversionesjl22_site_fixed_roles() );
		/* translators: %s: role names */
		inversionesjl22_site_roles_notice( 'success', array( sprintf( __( 'Permisos de %s restaurados a los valores iniciales.', 'inversionesjl22-site' ), implode( ', ', $names ) ) ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked above.
		inversionesjl22_site_roles_redirect( isset( $_POST['role'] ) ? inversionesjl22_site_roles_posted_role() : '' );
	}
);

function inversionesjl22_site_roles_screen(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$config = inversionesjl22_site_config();
	$roles  = inversionesjl22_site_editable_roles();
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read only, to choose the view.
	$new     = isset( $_GET['new'] ) || ! $roles;
	$current = isset( $_GET['role'] ) ? sanitize_key( wp_unslash( $_GET['role'] ) ) : '';
	// phpcs:enable
	if ( ! isset( $roles[ $current ] ) ) {
		$current = (string) array_key_first( $roles );
	}
	$page   = admin_url( 'admin.php?page=' . INVERSIONESJL22_SITE_ROLES_PAGE );
	$post   = admin_url( 'admin-post.php' );
	$notice = get_transient( 'inversionesjl22_site_roles_notice_' . get_current_user_id() );
	delete_transient( 'inversionesjl22_site_roles_notice_' . get_current_user_id() );
	?>
	<div class="wrap jl-settings">
		<div class="jl-settings__head">
			<div>
				<h1 class="jl-settings__title"><?php esc_html_e( 'Roles y permisos', 'inversionesjl22-site' ); ?></h1>
				<p class="jl-settings__lead"><?php esc_html_e( 'Qué pedidos ve cada rol, qué puede hacer con ellos y desde dónde.', 'inversionesjl22-site' ); ?></p>
			</div>
			<div class="jl-settings__actions">
				<form method="post" action="<?php echo esc_url( $post ); ?>" data-jl-confirm="<?php esc_attr_e( '¿Restaurar los permisos iniciales de los roles de la tienda? Se pierde lo que hayas cambiado en ellos.', 'inversionesjl22-site' ); ?>">
					<input type="hidden" name="action" value="inversionesjl22_site_reset_roles">
					<?php if ( ! $new ) : ?>
						<input type="hidden" name="role" value="<?php echo esc_attr( $current ); ?>">
					<?php endif; ?>
					<?php wp_nonce_field( 'inversionesjl22_site_reset_roles' ); ?>
					<button type="submit" class="jl-button jl-button--outline"><?php esc_html_e( 'Restaurar valores iniciales', 'inversionesjl22-site' ); ?></button>
				</form>
				<?php if ( ! $new ) : ?>
					<button type="submit" form="jl-role-form" class="jl-button jl-button--primary"><?php esc_html_e( 'Guardar cambios', 'inversionesjl22-site' ); ?></button>
				<?php endif; ?>
			</div>
		</div>

		<?php // .wp-header-end: where wp-admin moves the other plugins' notices. ?>
		<hr class="wp-header-end">

		<?php inversionesjl22_site_settings_tabs( INVERSIONESJL22_SITE_ROLES_PAGE ); ?>

		<?php if ( is_array( $notice ) && ! empty( $notice['messages'] ) ) : ?>
			<div class="jl-notice jl-notice--<?php echo esc_attr( 'error' === $notice['type'] ? 'error' : 'success' ); ?>" role="<?php echo esc_attr( 'error' === $notice['type'] ? 'alert' : 'status' ); ?>">
				<?php foreach ( (array) $notice['messages'] as $message ) : ?>
					<p><?php echo esc_html( $message ); ?></p>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<nav class="jl-rolebar" aria-label="<?php esc_attr_e( 'Rol', 'inversionesjl22-site' ); ?>">
			<span class="jl-rolebar__label" aria-hidden="true"><?php esc_html_e( 'Rol', 'inversionesjl22-site' ); ?></span>
			<?php foreach ( $roles as $slug => $unused ) : ?>
				<a class="jl-tab" href="<?php echo esc_url( add_query_arg( 'role', $slug, $page ) ); ?>" <?php echo ! $new && $slug === $current ? 'aria-current="page"' : ''; ?>><?php echo esc_html( inversionesjl22_site_role_name( (string) $slug ) ); ?></a>
			<?php endforeach; ?>
			<a class="jl-tab jl-tab--add" href="<?php echo esc_url( add_query_arg( 'new', '1', $page ) ); ?>" <?php echo $new ? 'aria-current="page"' : ''; ?>><?php esc_html_e( '+ Nuevo rol', 'inversionesjl22-site' ); ?></a>
			<span class="jl-rolebar__note"><?php esc_html_e( 'El administrador no tiene restricciones.', 'inversionesjl22-site' ); ?></span>
		</nav>
		<p class="jl-settings__hint">
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: URL of the users screen */
					__( 'Aquí se decide qué puede hacer cada rol. Para dar un rol a una persona, edita su perfil en <a href="%s">Usuarios</a>.', 'inversionesjl22-site' ),
					esc_url( admin_url( 'users.php' ) )
				),
				array( 'a' => array( 'href' => array() ) )
			);
			?>
		</p>

		<?php
		if ( $new ) {
			inversionesjl22_site_roles_new_form( $roles );
		} else {
			inversionesjl22_site_roles_role_form( $current, $roles[ $current ], $config );
		}
		?>
	</div>
	<?php
}

/**
 * The form that creates a role.
 */
function inversionesjl22_site_roles_new_form( array $roles ): void {
	?>
	<form class="jl-card jl-card--narrow" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="inversionesjl22_site_create_role">
		<?php wp_nonce_field( 'inversionesjl22_site_create_role' ); ?>
		<h2><?php esc_html_e( 'Nuevo rol', 'inversionesjl22-site' ); ?></h2>
		<p class="jl-field">
			<label for="jl-role-name"><?php esc_html_e( 'Nombre del rol', 'inversionesjl22-site' ); ?></label>
			<input type="text" id="jl-role-name" name="role_name" maxlength="40" required aria-describedby="jl-role-name-help">
			<span class="jl-field__help" id="jl-role-name-help"><?php esc_html_e( 'Como lo verás en Usuarios. Por ejemplo: Despachos.', 'inversionesjl22-site' ); ?></span>
		</p>
		<p class="jl-field">
			<label for="jl-role-base"><?php esc_html_e( 'Copiar las capacidades de', 'inversionesjl22-site' ); ?></label>
			<select id="jl-role-base" name="role_base" aria-describedby="jl-role-base-help">
				<?php foreach ( $roles as $slug => $unused ) : ?>
					<?php if ( get_role( (string) $slug ) ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( 'storage_manager', $slug ); ?>><?php echo esc_html( inversionesjl22_site_role_name( (string) $slug ) ); ?></option>
					<?php endif; ?>
				<?php endforeach; ?>
			</select>
			<span class="jl-field__help" id="jl-role-base-help"><?php esc_html_e( 'Lo que WordPress le deja hacer (entrar al panel, gestionar pedidos). El rol nuevo empieza sin ver ningún pedido y solo con el Escritorio en el menú: después marcas lo que le corresponde.', 'inversionesjl22-site' ); ?></span>
		</p>
		<p class="jl-settings__submit">
			<button type="submit" class="jl-button jl-button--primary"><?php esc_html_e( 'Crear rol', 'inversionesjl22-site' ); ?></button>
		</p>
	</form>
	<?php
}

/**
 * The form of a role: the matrix, its own steps and its menu.
 */
function inversionesjl22_site_roles_role_form( string $role, array $rules, array $config ): void {
	$name     = inversionesjl22_site_role_name( $role );
	$contexts = inversionesjl22_site_role_contexts();
	$exclude  = (array) ( $rules['exclude'] ?? array() );
	$saved    = array_values( array_filter( (array) ( $rules['menu'] ?? array() ), 'is_string' ) );
	$options  = inversionesjl22_site_roles_menu_options();
	$post     = admin_url( 'admin-post.php' );
	$in       = count( wp_list_filter( $contexts, array( 'group' => 'in' ) ) );

	// What the save may accept as a menu key (admin-post.php builds no menu).
	set_transient( 'inversionesjl22_site_roles_menu_' . get_current_user_id(), array_keys( $options ), DAY_IN_SECONDS );

	$steps = array();
	foreach ( (array) ( $rules['flow'] ?? array() ) as $to => $origins ) {
		foreach ( (array) $origins as $from ) {
			if ( isset( $config['statuses'][ $to ], $config['statuses'][ $from ] ) ) {
				$steps[] = array( (string) $from, (string) $to );
			}
		}
	}
	?>
	<form id="jl-role-form" class="jl-role" method="post" action="<?php echo esc_url( $post ); ?>">
		<input type="hidden" name="action" value="inversionesjl22_site_save_role">
		<input type="hidden" name="role" value="<?php echo esc_attr( $role ); ?>">
		<?php wp_nonce_field( 'inversionesjl22_site_save_role' ); ?>

		<div class="jl-matrix-box">
			<table class="jl-matrix">
				<caption class="screen-reader-text">
					<?php
					/* translators: %s: role name */
					echo esc_html( sprintf( __( 'Permisos de %s por estado', 'inversionesjl22-site' ), $name ) );
					?>
				</caption>
				<thead>
					<tr class="jl-matrix__groups">
						<td></td>
						<th scope="colgroup" colspan="<?php echo esc_attr( (string) ( 1 + $in ) ); ?>" class="jl-matrix__edge"><?php esc_html_e( 'Pedidos en este estado', 'inversionesjl22-site' ); ?></th>
						<th scope="colgroup" colspan="<?php echo esc_attr( (string) ( count( $contexts ) - $in ) ); ?>" class="jl-matrix__edge"><?php esc_html_e( 'Mover pedidos a este estado desde', 'inversionesjl22-site' ); ?></th>
					</tr>
					<tr>
						<th scope="col" class="jl-matrix__status"><?php esc_html_e( 'Estado', 'inversionesjl22-site' ); ?></th>
						<th scope="col" class="jl-matrix__edge"><?php esc_html_e( 'Ver los pedidos', 'inversionesjl22-site' ); ?></th>
						<?php
						$group = 'in';
						foreach ( $contexts as $context ) :
							?>
							<th scope="col" <?php echo $context['group'] !== $group ? 'class="jl-matrix__edge"' : ''; ?>><?php echo esc_html( $context['label'] ); ?></th>
							<?php
							$group = $context['group'];
						endforeach;
						?>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $config['statuses'] as $slug => $unused ) :
						$slug   = (string) $slug;
						$status = inversionesjl22_site_status_label( $slug );
						$sees   = ! empty( $rules['statuses'][ $slug ] );
						$field  = 'perm[' . $slug . ']';
						?>
						<tr data-jl-row class="<?php echo $sees ? '' : 'is-off'; ?>">
							<th scope="row" class="jl-matrix__status">
								<?php echo inversionesjl22_site_status_pill( $slug ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the function. ?>
								<input type="hidden" name="<?php echo esc_attr( $field ); ?>[row]" value="1" data-jl-cell <?php disabled( ! $sees ); ?>>
							</th>
							<td class="jl-matrix__edge">
								<?php /* translators: %s: status name */ ?>
								<label class="jl-matrix__box"><input type="checkbox" name="<?php echo esc_attr( $field ); ?>[see]" value="1" data-jl-see aria-label="<?php echo esc_attr( sprintf( __( 'Ver los pedidos: %s', 'inversionesjl22-site' ), $status ) ); ?>" <?php checked( $sees ); ?>></label>
							</td>
							<?php
							$group = 'in';
							foreach ( $contexts as $context => $column ) :
								?>
								<td <?php echo $column['group'] !== $group ? 'class="jl-matrix__edge"' : ''; ?>>
									<label class="jl-matrix__box"><input type="checkbox" name="<?php echo esc_attr( $field . '[' . $context . ']' ); ?>" value="1" data-jl-cell aria-label="<?php echo esc_attr( $column['label'] . ': ' . $status ); ?>" <?php checked( ! in_array( $slug, (array) ( $exclude[ $context ] ?? array() ), true ) ); ?> <?php disabled( ! $sees ); ?>></label>
								</td>
								<?php
								$group = $column['group'];
							endforeach;
							?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p class="jl-settings__hint"><?php esc_html_e( 'Si el rol no ve un estado, el resto de su fila queda apagado y conserva lo que tenía marcado. «Mover a» respeta además el flujo: solo aplica a los pasos que existen.', 'inversionesjl22-site' ); ?></p>

		<div class="jl-cards">
			<section class="jl-card" aria-labelledby="jl-role-steps">
				<h2 id="jl-role-steps"><?php esc_html_e( 'Pasos propios de este rol', 'inversionesjl22-site' ); ?></h2>
				<p class="jl-settings__hint"><?php esc_html_e( 'Además del flujo general. Por ejemplo, un rol que puede devolver un pedido de Procesando a En espera.', 'inversionesjl22-site' ); ?></p>
				<?php if ( $steps ) : ?>
					<ul class="jl-steps">
						<?php foreach ( $steps as $step ) : ?>
							<li>
								<span class="jl-steps__path">
									<?php echo inversionesjl22_site_status_pill( $step[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the function. ?>
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
									<span class="screen-reader-text"><?php esc_html_e( 'a', 'inversionesjl22-site' ); ?></span>
									<?php echo inversionesjl22_site_status_pill( $step[1] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the function. ?>
								</span>
								<label class="jl-option">
									<input type="checkbox" name="flow_remove[]" value="<?php echo esc_attr( $step[0] . ':' . $step[1] ); ?>">
									<?php esc_html_e( 'Quitar al guardar', 'inversionesjl22-site' ); ?>
									<?php /* translators: 1: origin status, 2: destination status */ ?>
									<span class="screen-reader-text"><?php echo esc_html( sprintf( __( 'el paso de %1$s a %2$s', 'inversionesjl22-site' ), inversionesjl22_site_status_label( $step[0] ), inversionesjl22_site_status_label( $step[1] ) ) ); ?></span>
								</label>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<?php /* translators: %s: role name */ ?>
					<p class="jl-card__empty"><?php echo esc_html( sprintf( __( '%s no tiene pasos propios.', 'inversionesjl22-site' ), $name ) ); ?></p>
				<?php endif; ?>
				<fieldset class="jl-step-add">
					<legend><?php esc_html_e( 'Añadir un paso', 'inversionesjl22-site' ); ?></legend>
					<?php
					$selects = array(
						'flow_from' => __( 'De', 'inversionesjl22-site' ),
						'flow_to'   => __( 'A', 'inversionesjl22-site' ),
					);
					foreach ( $selects as $field => $label ) :
						?>
						<p class="jl-field">
							<label for="jl-<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $label ); ?></label>
							<select id="jl-<?php echo esc_attr( $field ); ?>" name="<?php echo esc_attr( $field ); ?>">
								<option value=""><?php esc_html_e( '— Ninguno —', 'inversionesjl22-site' ); ?></option>
								<?php foreach ( $config['statuses'] as $slug => $unused ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( inversionesjl22_site_status_label( (string) $slug ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
					<?php endforeach; ?>
					<p class="jl-field__help"><?php esc_html_e( 'Elige los dos estados y guarda: el paso se añade a la lista.', 'inversionesjl22-site' ); ?></p>
				</fieldset>
			</section>

			<section class="jl-card" aria-labelledby="jl-role-menu">
				<h2 id="jl-role-menu"><?php esc_html_e( 'Pantallas que ve en el menú', 'inversionesjl22-site' ); ?></h2>
				<p class="jl-settings__hint"><?php esc_html_e( 'Lo que no esté marcado no aparece y tampoco se abre escribiendo la dirección. El rol necesita además la capacidad de WordPress de esa pantalla.', 'inversionesjl22-site' ); ?></p>
				<?php
				$option = static function ( string $key, string $label, bool $on ) {
					$home = 'index.php' === $key;
					?>
					<label class="jl-option">
						<input type="checkbox" name="menu[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $on || $home ); ?> <?php disabled( $home ); ?> <?php echo $home ? 'aria-describedby="jl-role-menu-home"' : ''; ?>>
						<?php echo esc_html( $label ); ?>
					</label>
					<?php
				};
				// Every screen of the menu by its top-level menu (the Escritorio entry is the fixed
				// one above). Ticked ones are the role's saved ones; one it has that is not in the
				// menu now (its plugin is off) goes last, by its address.
				$groups = array();
				foreach ( $options as $key => $entry ) {
					if ( 'index.php' !== $key ) {
						$groups[ $entry['group'] ][ $key ] = $entry['label'];
					}
				}
				foreach ( $saved as $key ) {
					if ( 'index.php' !== $key && ! isset( $options[ $key ] ) ) {
						$groups[ __( 'Otras (su plugin está desactivado)', 'inversionesjl22-site' ) ][ $key ] = $key;
					}
				}
				?>
				<div class="jl-options">
					<?php
					// By the menu's name (Escritorio), not its first entry's (Inicio).
					$option( 'index.php', $options['index.php']['group'] ?? __( 'Escritorio', 'inversionesjl22-site' ), true );
					?>
				</div>
				<p class="jl-field__help" id="jl-role-menu-home"><?php esc_html_e( 'El Escritorio va siempre: es a donde llega el rol cuando una pantalla no es suya.', 'inversionesjl22-site' ); ?></p>
				<?php if ( $groups ) : ?>
					<div class="jl-menu-picker" data-jl-menu-picker>
						<p class="jl-field jl-menu-picker__search" data-jl-menu-search hidden>
							<label for="jl-role-menu-search"><?php esc_html_e( 'Buscar una pantalla', 'inversionesjl22-site' ); ?></label>
							<?php // No name: the search is not part of what the form saves. ?>
							<input type="search" id="jl-role-menu-search" autocomplete="off" aria-controls="jl-role-menu-panel" data-jl-menu-query>
						</p>
						<p class="jl-menu-picker__status" role="status" data-jl-menu-status></p>
						<div class="jl-menu-picker__panel" id="jl-role-menu-panel" data-jl-menu-panel>
							<?php foreach ( $groups as $group => $entries ) : ?>
								<?php
								$total   = count( $entries );
								$checked = 0;
								foreach ( $entries as $key => $unused ) {
									if ( in_array( (string) $key, $saved, true ) ) {
										++$checked;
									}
								}
								?>
								<details class="jl-menu-group<?php echo $checked ? ' has-checked' : ''; ?>" data-jl-menu-group>
									<summary>
										<span class="jl-menu-group__name"><?php echo esc_html( $group ); ?></span>
										<span class="jl-menu-group__count" data-jl-menu-count data-total="<?php echo esc_attr( (string) $total ); ?>">
											<?php /* translators: 1: how many screens of the menu are ticked, 2: how many it has */ ?>
											<?php echo esc_html( sprintf( __( '%1$d de %2$d marcadas', 'inversionesjl22-site' ), $checked, $total ) ); ?>
										</span>
									</summary>
									<div class="jl-menu-group__items">
										<?php
										foreach ( $entries as $key => $label ) {
											$option( (string) $key, $label, in_array( (string) $key, $saved, true ) );
										}
										?>
									</div>
								</details>
							<?php endforeach; ?>
							<p class="jl-menu-picker__empty" data-jl-menu-empty hidden><?php esc_html_e( 'Ninguna pantalla coincide con la búsqueda.', 'inversionesjl22-site' ); ?></p>
						</div>
					</div>
				<?php endif; ?>
			</section>

			<section class="jl-card" aria-labelledby="jl-role-extras">
				<h2 id="jl-role-extras"><?php esc_html_e( 'Otros permisos', 'inversionesjl22-site' ); ?></h2>
				<div class="jl-card__options">
				<div class="jl-card__option">
				<h3 id="jl-role-qr"><?php esc_html_e( 'Escaneo del QR de la factura', 'inversionesjl22-site' ); ?></h3>
				<label class="jl-option">
					<input type="checkbox" name="qr_scan" value="1" aria-describedby="jl-role-qr-help" <?php checked( inversionesjl22_site_role_scans_qr( $role ) ); ?>>
					<?php esc_html_e( 'Puede escanear el QR', 'inversionesjl22-site' ); ?>
				</label>
				<p class="jl-field__help" id="jl-role-qr-help"><?php esc_html_e( 'Escanear el QR de la factura pasa el pedido de Impreso a Completado y muestra el aviso del escaneo en sus pantallas del sitio. Solo completa el pedido si el rol ve Impreso y Completado y su flujo tiene ese paso.', 'inversionesjl22-site' ); ?></p>
				</div>

				<div class="jl-card__option">
				<h3 id="jl-role-combine"><?php esc_html_e( 'Combinar pedidos', 'inversionesjl22-site' ); ?></h3>
				<label class="jl-option">
					<input type="checkbox" name="combine" value="1" aria-describedby="jl-role-combine-help" <?php checked( inversionesjl22_site_role_can_combine( $role ) ); ?>>
					<?php esc_html_e( 'Puede combinar pedidos', 'inversionesjl22-site' ); ?>
				</label>
				<p class="jl-field__help" id="jl-role-combine-help"><?php esc_html_e( 'La acción en lote «Combinar pedidos» une dos o más pedidos del mismo cliente, moneda y estado en uno nuevo con ese estado; los originales quedan en Combinado, enlazados al nuevo. Solo combina pedidos de estados que el rol ve.', 'inversionesjl22-site' ); ?></p>
				</div>
				</div>
			</section>
		</div>

		<p class="jl-settings__submit">
			<button type="submit" class="jl-button jl-button--primary"><?php esc_html_e( 'Guardar cambios', 'inversionesjl22-site' ); ?></button>
		</p>
	</form>

	<?php if ( ! empty( $rules['custom'] ) && ! in_array( $role, inversionesjl22_site_fixed_roles(), true ) ) : ?>
		<?php /* translators: %s: role name */ ?>
		<form class="jl-card jl-card--narrow" method="post" action="<?php echo esc_url( $post ); ?>" data-jl-confirm="<?php echo esc_attr( sprintf( __( '¿Eliminar el rol %s? No se puede deshacer.', 'inversionesjl22-site' ), $name ) ); ?>">
			<input type="hidden" name="action" value="inversionesjl22_site_delete_role">
			<input type="hidden" name="role" value="<?php echo esc_attr( $role ); ?>">
			<?php wp_nonce_field( 'inversionesjl22_site_delete_role' ); ?>
			<h2><?php esc_html_e( 'Eliminar este rol', 'inversionesjl22-site' ); ?></h2>
			<p class="jl-settings__hint"><?php esc_html_e( 'Solo se puede eliminar un rol creado aquí y que no tenga ningún usuario.', 'inversionesjl22-site' ); ?></p>
			<p class="jl-settings__submit">
				<?php /* translators: %s: role name */ ?>
				<button type="submit" class="jl-button jl-button--outline"><?php echo esc_html( sprintf( __( 'Eliminar el rol %s', 'inversionesjl22-site' ), $name ) ); ?></button>
			</p>
		</form>
	<?php endif; ?>
	<?php
}
