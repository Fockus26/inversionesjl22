<?php
/**
 * Warehouse QR: the QR on the 80 mm invoice (generateQrCode()) points to
 * ?action=qr_scan&order_id=N&sig=<signature of the order> (inversionesjl22_site_qr_url()). A link
 * without a valid signature (the invoices printed before it) changes nothing on its own: it shows
 * a confirmation page whose button posts with a nonce. Scanning it with a role that has the QR (Inversiones JL22 › Roles y
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
        // phpcs:disable WordPress.Security.NonceVerification -- the GET is authorised by the QR's
        // signature, the POST by its nonce, both checked below before anything changes.
        if (empty($_GET['action']) || $_GET['action'] !== 'qr_scan') {
            return;
        }

        if (empty($_GET['order_id'])) {
            return;
        }

        // Nunca guardar en caché la respuesta del escaneo (ni en Cloudflare ni en el navegador)
        nocache_headers();

        $order_id  = absint($_GET['order_id']);
        $signature = isset($_GET['sig']) ? sanitize_key(wp_unslash($_GET['sig'])) : '';
        $is_post   = isset($_SERVER['REQUEST_METHOD']) && 'POST' === strtoupper(sanitize_key(wp_unslash($_SERVER['REQUEST_METHOD'])));

        if (!is_user_logged_in()) {
            // Iniciar sesión y volver a este mismo QR (con su firma, si la trae)
            $qrUrl = add_query_arg(array_filter([
                'action'   => 'qr_scan',
                'order_id' => $order_id,
                'sig'      => $signature,
            ]), home_url('/'));
            wp_safe_redirect(wp_login_url($qrUrl));
            exit;
        }

        // URL única para que la portada no salga de caché y muestre la transición
        $doneUrl = add_query_arg('qr_scan_done', time(), home_url('/'));

        if (!inversionesjl22_site_user_can_scan_qr()) {
            wp_safe_redirect($doneUrl);
            exit;
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_safe_redirect($doneUrl);
            exit;
        }

        // Who may scan is checked above; here, that the scan is really one. The new invoices carry
        // a signature of the order in the QR: one scan does it, as always. The QR of an invoice
        // printed before the signature (or any link without a valid one, which a third party could
        // put in front of the warehouse) changes nothing until the person confirms it on a page
        // of the site, whose button posts with a nonce.
        if ($is_post) {
            $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
            if (!wp_verify_nonce($nonce, inversionesjl22_site_qr_nonce_action($order_id))) {
                wp_die(
                    esc_html__('La confirmación del escaneo caducó o no es válida. Escanea el código QR otra vez.', 'inversionesjl22-site'),
                    esc_html__('Escaneo no confirmado', 'inversionesjl22-site'),
                    ['response' => 403, 'link_url' => esc_url(home_url('/')), 'link_text' => esc_html__('Ir al inicio', 'inversionesjl22-site')]
                );
            }
        } elseif (!inversionesjl22_site_qr_signature_is_valid($order, $signature)) {
            inversionesjl22_site_qr_confirm_screen($order);
            exit;
        }
        // phpcs:enable

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
        } elseif (function_exists('inversionesjl22_site_is_combined') && inversionesjl22_site_is_combined($status)) {
            // A combined order is not a live one: nothing changes (combined-orders.php refuses
            // it anyway), and the notice points to the order it went into.
            $into = (int) $order->get_meta(INVERSIONESJL22_SITE_COMBINED_INTO);
            inversionesjl22_site_qr_add_event(
                $into ? sprintf('La orden se combinó en la #%d: escanea la factura de esa orden', $into) : 'La orden se combinó en otra: escanea la factura de esa orden',
                'rgb(229, 229, 229)'
            );
        }

        wp_safe_redirect($doneUrl);
        exit;
    }
}

/**
 * The signature the invoice's QR carries (&sig=): an HMAC of the order's ID and its order key with
 * the site's auth salt, so only the site can make it and it holds for that order alone. 20 hex
 * characters (80 bits) keep the QR small enough for the 20 mm square of the 58 mm invoice.
 * '' when the order has no key: its QR then goes through the confirmation page.
 */
function inversionesjl22_site_qr_signature(WC_Order $order): string {
    $key = (string) $order->get_order_key();
    if ('' === $key) {
        return '';
    }
    return substr(hash_hmac('sha256', 'qr_scan|' . $order->get_id() . '|' . $key, wp_salt('auth')), 0, 20);
}

function inversionesjl22_site_qr_signature_is_valid(WC_Order $order, string $signature): bool {
    $expected = inversionesjl22_site_qr_signature($order);
    return '' !== $expected && '' !== $signature && hash_equals($expected, $signature);
}

/**
 * The link the invoice's QR holds. The host stays the shop's, as the invoice always printed it.
 */
function inversionesjl22_site_qr_url(WC_Order $order): string {
    return add_query_arg(array_filter([
        'action'   => 'qr_scan',
        'order_id' => $order->get_id(),
        'sig'      => inversionesjl22_site_qr_signature($order),
    ]), 'https://inversionesjl22.com');
}

function inversionesjl22_site_qr_nonce_action(int $order_id): string {
    return 'inversionesjl22_site_qr_scan_' . $order_id;
}

/**
 * The page a QR without a valid signature gets: what the scan would do and one button that posts
 * it with a nonce. A page of its own, not the theme's: it is read on the warehouse phone and must
 * load fast, and the response is not cacheable (nocache_headers() above).
 */
function inversionesjl22_site_qr_confirm_screen(WC_Order $order): void {
    $order_id  = $order->get_id();
    $status    = $order->get_status();
    $completes = 'impressed' === $status;
    $action    = add_query_arg(['action' => 'qr_scan', 'order_id' => $order_id], home_url('/'));
    /* translators: %s: order number */
    $title     = sprintf(__('Confirmar el escaneo del pedido #%s', 'inversionesjl22-site'), $order->get_order_number());

    status_header(200);
    header('Content-Type: text/html; charset=' . get_option('blog_charset'));
    header('X-Robots-Tag: noindex, nofollow');
    ?><!doctype html>
<html lang="es-CO">
<head>
<meta charset="<?php echo esc_attr(get_option('blog_charset')); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html($title . ' · ' . get_bloginfo('name')); ?></title>
<style>
    :root { --jl-text: #1f1a17; --jl-muted: #4d4540; --jl-bg: #ffffff; --jl-surface: #faf6f2; --jl-border: #d9cfc6; --jl-primary: #a4521a; --jl-primary-hover: #8a4415; --jl-on-primary: #ffffff; --jl-focus: #1f1a17; }
    * { box-sizing: border-box; }
    body { margin: 0; background: var(--jl-bg); color: var(--jl-text); font: 1rem/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
    main { max-width: 30rem; margin: 0 auto; padding: 2rem 1rem; }
    h1 { font-size: 1.375rem; line-height: 1.3; margin: 0 0 1rem; overflow-wrap: anywhere; }
    p { margin: 0 0 1rem; }
    .jl-qr-status { background: var(--jl-surface); border: 1px solid var(--jl-border); border-radius: 0.5rem; padding: 0.75rem 1rem; }
    .jl-qr-note { color: var(--jl-muted); font-size: 0.9375rem; }
    .jl-qr-actions { display: flex; flex-wrap: wrap; gap: 0.75rem 1.5rem; align-items: center; margin-top: 1.5rem; }
    button { font: inherit; font-weight: 600; min-height: 2.75rem; padding: 0.625rem 1.25rem; border: 0; border-radius: 0.375rem; background: var(--jl-primary); color: var(--jl-on-primary); cursor: pointer; }
    button:hover { background: var(--jl-primary-hover); }
    a { color: var(--jl-primary); text-decoration: underline; text-underline-offset: 0.15em; display: inline-block; padding: 0.625rem 0; }
    button:focus-visible, a:focus-visible { outline: 3px solid var(--jl-focus); outline-offset: 3px; }
</style>
</head>
<body>
<main>
    <h1><?php echo esc_html($title); ?></h1>
    <p class="jl-qr-status"><?php
        /* translators: %s: order status */
        echo esc_html(sprintf(__('Estado actual: %s', 'inversionesjl22-site'), wc_get_order_status_name($status)));
    ?></p>
    <p><?php
        echo esc_html($completes
            ? __('Al confirmar, el pedido pasa a Completado.', 'inversionesjl22-site')
            : __('Al confirmar se registra el escaneo; el estado del pedido no cambia.', 'inversionesjl22-site'));
    ?></p>
    <p class="jl-qr-note"><?php esc_html_e('Este código QR es de una factura anterior y pide un paso más. Las facturas nuevas completan el pedido con un solo escaneo.', 'inversionesjl22-site'); ?></p>
    <form method="post" action="<?php echo esc_url($action); ?>" class="jl-qr-actions">
        <?php wp_nonce_field(inversionesjl22_site_qr_nonce_action($order_id)); ?>
        <button type="submit"><?php echo esc_html($completes ? __('Completar pedido', 'inversionesjl22-site') : __('Registrar escaneo', 'inversionesjl22-site')); ?></button>
        <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Cancelar', 'inversionesjl22-site'); ?></a>
    </form>
</main>
</body>
</html>
<?php
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

/**
 * The QR of $text as a PNG data URI, or false if it cannot be made (the invoice then prints
 * without it, as it did when the old service did not answer).
 *
 * Made here with chillerlan/php-qrcode (vendor/autoload.php): before, the image came from
 * api.qrserver.com, which received the site's link and the order ID of every invoice and left
 * the QR depending on a third party being up. The PNG is written below without GD (only zlib),
 * so it does not depend on an image extension of the host: one byte per pixel, black or white,
 * at least $size pixels wide and a whole number of pixels per module, so the modules stay sharp
 * when Dompdf scales it to the invoice's 20 mm. ECC level M (15 %) for the thermal printers.
 *
 * @param string $text
 * @param int    $size minimum width in pixels.
 * @return string|false
 */
function generateQrCode($text = '', $size = 100) {
    if ('' === (string) $text || !function_exists('gzcompress')) {
        return false;
    }
    require_once INVERSIONESJL22_SITE_DIR . 'vendor/autoload.php';

    try {
        $options = new \InversionesJL22Site\Vendor\chillerlan\QRCode\QROptions([
            'eccLevel'      => \InversionesJL22Site\Vendor\chillerlan\QRCode\Common\EccLevel::M,
            'addQuietzone'  => true,
            'quietzoneSize' => 4,
        ]);
        $qr = new \InversionesJL22Site\Vendor\chillerlan\QRCode\QRCode($options);
        $qr->addByteSegment((string) $text);
        $matrix = $qr->getQRMatrix()->getMatrix(true);
    } catch (\Throwable $e) {
        return false;
    }

    $modules = count($matrix);
    if ($modules < 21) {
        return false;
    }
    $scale = max(4, (int) ceil(max(1, (int) $size) * 4 / $modules)); // ~4× $size: crisp in print
    $width = $modules * $scale;

    $raw = '';
    foreach ($matrix as $row) {
        $line = "\0"; // filter: none
        foreach ($row as $dark) {
            $line .= str_repeat($dark ? "\x00" : "\xFF", $scale);
        }
        $raw .= str_repeat($line, $scale);
    }

    $chunk = static function (string $type, string $data): string {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    };
    $png = "\x89PNG\r\n\x1a\n"
        . $chunk('IHDR', pack('NNCCCCC', $width, $width, 8, 0, 0, 0, 0)) // 8-bit greyscale
        . $chunk('IDAT', gzcompress($raw, 9))
        . $chunk('IEND', '');

    return 'data:image/png;base64,' . base64_encode($png);
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
