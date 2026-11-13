<?php
/**
 * Warehouse QR: the QR on the 80 mm invoice (generateQrCode()) points to
 * ?action=qr_scan&order_id=N; scanning it with a role that has the QR (WooCommerce › Roles y
 * permisos, inversionesjl22_site_user_can_scan_qr(); by default storage_manager) moves an Impreso
 * order to Completado, and every screen of those roles open on the site shows the result (checked
 * every 3 s, assets/js/qr-scan.js). The move also needs the role's own rules: it sees Impreso and
 * Completado and its flow goes from one to the other (inversionesjl22_site_qr_can_complete()). Each scan is kept for two minutes with its time
 * (inversionesjl22_site_qr_events()); every screen asks for the scans after the last one it saw.
 * Before, one transient held the last scan and the first screen to ask deleted it: with the site
 * open on several devices only one of them, at random, showed it. Also the invoice filters that live in the same snippet: unit price
 * with IVA, and "IDENTIFICACIÓN:" instead of "CÉDULA:" when the cédula is missing or short.
 *
 * Moved from WPCodeBox › PDF › Scan Qr (snippet 58) and Scan Qr_1 (snippet 62, the JS that
 * WPCodeBox printed in the public <head>; here it is enqueued in the same place). Only change:
 * the two match() are lookup arrays, for PHP 7.4. The snippet's own change log is kept below.
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_admin() ) {
	add_action(
		'wp_enqueue_scripts',
		static function () {
			// Only the roles with the QR poll for scans (the footer below calls it for them
			// alone); every other visitor downloaded it for nothing.
			if ( ! inversionesjl22_site_user_can_scan_qr() ) {
				return;
			}
			wp_enqueue_script(
				'inversionesjl22-qr-scan',
				INVERSIONESJL22_SITE_URL . 'assets/js/qr-scan.js',
				array(),
				inversionesjl22_site_asset_version( 'assets/js/qr-scan.js' ),
				false
			);
		}
	);
}

/*
 * WPCodeBox > PDF > "Scan Qr" (id 58) — PHP, Plugins Loaded.
 *
 * Cambios respecto a la versión anterior:
 * - El sitio pasa por Cloudflare y el HTML se entrega con "Cache-Control: public, max-age=2678400" (31 días).
 *   Tras escanear se redirigía a home_url('/'), y el navegador podía mostrar una copia guardada de la portada
 *   sin sesión: parecía que te sacaba de la sesión y no salía la transición (el script solo se imprime para
 *   storage_manager). Ahora se envían cabeceras no-cache y se redirige a una URL única (?qr_scan_done=...).
 * - Si quien escanea no tiene sesión iniciada, se le manda al login y vuelve al QR, en vez de llevarlo a la portada.
 * - Los mensajes ahora dicen lo que realmente pasa: el escaneo solo cambia Impreso → Completado.
 */

if ( !is_admin() ) {
    add_action('wp_footer', function () {
        if (!inversionesjl22_site_user_can_scan_qr()) {
            return;
        }
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                checkQrScanJs('<?php echo esc_url(admin_url('admin-ajax.php')); ?>');
            });
        </script>
        <?php
    });

    add_action('template_redirect', 'scanQrUI');
    function scanQrUI() {
        if (empty($_GET['action']) || $_GET['action'] !== 'qr_scan') {
            return;
        }

        if (empty($_GET['order_id'])) {
            return;
        }

        // Nunca guardar en caché la respuesta del escaneo (ni en Cloudflare ni en el navegador)
        nocache_headers();

        if (!is_user_logged_in()) {
            // Iniciar sesión y volver a este mismo QR
            $qrUrl = add_query_arg([
                'action'   => 'qr_scan',
                'order_id' => intval($_GET['order_id']),
            ], home_url('/'));
            wp_safe_redirect(wp_login_url($qrUrl));
            exit;
        }

        // URL única para que la portada no salga de caché y muestre la transición
        $doneUrl = add_query_arg('qr_scan_done', time(), home_url('/'));

        if (!inversionesjl22_site_user_can_scan_qr()) {
            wp_safe_redirect($doneUrl);
            exit;
        }

        $order_id = intval($_GET['order_id']);
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_safe_redirect($doneUrl);
            exit;
        }

        $status = $order->get_status();

        // A role with the QR whose rules do not let it complete the order: nothing changes, and
        // the notice says so. (The other statuses keep their notices, as they always had.)
        if ($status === 'impressed' && !inversionesjl22_site_qr_can_complete(getUserPermissions())) {
            inversionesjl22_site_qr_add_event('Tu rol no puede completar esta orden', 'rgb(229, 229, 229)');
            wp_safe_redirect($doneUrl);
            exit;
        }

        if ($status === 'impressed') {
            $order->update_status('completed', 'Actualizado vía QR desde estado impreso');
            update_post_meta($order_id, '_qr_completed_at', current_time('mysql'));
            $order->add_order_note('La orden fue completada mediante escaneo de QR desde estado impreso.');
        }

        if (in_array($status, ['impressed', 'processing', 'completed', 'on-hold', 'cancelled'])) {
            // (Lookups instead of match(): this plugin supports PHP 7.4.)
            $colors = [
                'on-hold'   => 'rgb(248, 221, 167)',
                'processing'=> 'rgb(198, 225, 198)',
                'impressed'   => 'rgb(251, 191, 18)',
                'completed' => 'rgb(200, 215, 225)',
                'cancelled' => 'rgb(229, 229, 229)',
            ];
            $color = $colors[$status];

            $messages = [
                'impressed'   => 'La orden paso a completada',
                'processing'=> 'La orden aun se esta procesando',
                'completed' => 'La orden ya fue completada',
                'on-hold'   => 'La orden esta en espera',
                'cancelled' => 'La orden fue cancelada',
            ];
            $message = $messages[$status];

            inversionesjl22_site_qr_add_event($message, $color);
        }

        wp_safe_redirect($doneUrl);
        exit;
    }
}

/**
 * Whether the scanner's rules let the QR move an Impreso order to Completado: the role sees both
 * statuses and its flow (the global one plus its own steps) has that step. Without rules (an
 * administrator who also holds a role with the QR) the scan does it, as it always did.
 * storage_manager's defaults pass. The per-context exclusions do not apply: 'qr_actions' is the
 * PDF invoice button in the orders list (roles-actions.php), not the scan.
 *
 * @param array|null $ruleset getUserPermissions().
 */
function inversionesjl22_site_qr_can_complete($ruleset): bool {
    if ($ruleset === null) {
        return true;
    }
    if (empty($ruleset['impressed']) || empty($ruleset['completed'])) {
        return false;
    }
    return in_array('impressed', (array) ($ruleset['flow']['completed'] ?? []), true);
}

const INVERSIONESJL22_SITE_QR_EVENTS = 'inversionesjl22_site_qr_events';
const INVERSIONESJL22_SITE_QR_KEEP    = 120; // seconds a scan stays visible to the screens

/**
 * The recent scans, oldest first: [ ['at' => float (server time), 'message', 'color'], … ].
 */
function inversionesjl22_site_qr_events(): array {
    $events = get_transient(INVERSIONESJL22_SITE_QR_EVENTS);
    if (!is_array($events)) {
        return [];
    }
    $limit = microtime(true) - INVERSIONESJL22_SITE_QR_KEEP;
    return array_values(array_filter($events, static function ($event) use ($limit) {
        return isset($event['at']) && $event['at'] > $limit;
    }));
}

function inversionesjl22_site_qr_add_event(string $message, string $color): void {
    $events   = inversionesjl22_site_qr_events();
    $events[] = ['at' => microtime(true), 'message' => $message, 'color' => $color];
    set_transient(INVERSIONESJL22_SITE_QR_EVENTS, array_slice($events, -20), INVERSIONESJL22_SITE_QR_KEEP);
}

/*
 * ?action=check_qr_scan&since=<server time of the last poll, or 0 on the first>: the scans
 * after it, and the server's time for the next poll. Nothing is deleted: every screen gets them.
 * A first poll (since=0) gets the scans of the last 20 s: the device that scanned lands on the
 * home page after the scan and must still see it.
 * Without "since" (the old script, until its page is reloaded) it answers "nothing": the old
 * script would show the same scan again on every poll.
 */
add_action('wp_ajax_check_qr_scan', 'checkQrScanCallback');
function checkQrScanCallback() {
    nocache_headers();

    if (!inversionesjl22_site_user_can_scan_qr()) {
        wp_send_json_error(['message' => 'No autorizado']);
    }

    $now = microtime(true);
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read only.
    if (!isset($_GET['since'])) {
        wp_send_json_success(['scanned' => false, 'now' => $now]);
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read only.
    $since = (float) $_GET['since'];
    if ($since <= 0) {
        $since = $now - 20;
    }

    $events = array_values(array_filter(inversionesjl22_site_qr_events(), static function ($event) use ($since) {
        return $event['at'] > $since;
    }));

    wp_send_json_success([
        'scanned' => (bool) $events,
        'events'  => $events,
        'now'     => $now,
    ]);
}

function generateQrCode($text = '', $size = 100) {
    $encoded_text = urlencode($text);
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encoded_text}";

    $response = wp_remote_get( $qr_url );
    if ( ! is_wp_error( $response ) ) {
        $image_data = wp_remote_retrieve_body( $response );
        return 'data:image/png;base64,' . base64_encode( $image_data );
    }

    return false;
}

if (class_exists('WPO_WCPDF')) {
    add_filter( 'wpo_wcpdf_item_price', 'showIVAInvoices', 10, 3);
    function showIVAInvoices($price, $document, $item) {
        if ( $document->get_type() == 'invoice' ) {
            return wc_price( $item->get_total() / $item->get_quantity(), array('currency' => $document->order->get_currency()) );
        }
        return $price;
    }

    add_filter( 'wpo_wcpdf_billing_address', 'validateCedulaInvoices', 10, 2 );
    function validateCedulaInvoices( $address, $document ) {
        $cedula = $document->order->get_meta('_billing_cedula');
        if ( empty($cedula) || strlen($cedula) < 6 ) {
            $address = str_replace('CÉDULA:', 'IDENTIFICACIÓN:', $address);
        }
        return $address;
    }
}
