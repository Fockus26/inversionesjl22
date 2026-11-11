<?php
/**
 * Warehouse QR: the QR on the 80 mm invoice (generateQrCode()) points to
 * ?action=qr_scan&order_id=N; scanning it as storage_manager moves an Impreso order to
 * Completado, and every storage_manager screen open on the site shows the result (checked every
 * 3 s, assets/js/qr-scan.js). Also the invoice filters that live in the same snippet: unit price
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
			// Only the warehouse polls for scans (the footer below calls it for them alone);
			// every other visitor downloaded it for nothing.
			if ( ! is_user_logged_in() || ! current_user_can( 'storage_manager' ) ) {
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
        if (!current_user_can('storage_manager')) {
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

        if (!current_user_can('storage_manager')) {
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

            set_transient('qr_scan_active', [
                'scanned' => true,
                'color'   => $color,
                'message' => $message,
            ], 60);
        }

        wp_safe_redirect($doneUrl);
        exit;
    }
}

add_action('wp_ajax_check_qr_scan', 'checkQrScanCallback');
function checkQrScanCallback() {
    nocache_headers();

    if (!current_user_can('storage_manager')) {
        wp_send_json_error(['message' => 'No autorizado']);
    }

    $data = get_transient('qr_scan_active');

    if ($data && isset($data['scanned'])) {
        delete_transient('qr_scan_active');
        wp_send_json_success($data);
    }

    wp_send_json_success(['scanned' => false]);
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
