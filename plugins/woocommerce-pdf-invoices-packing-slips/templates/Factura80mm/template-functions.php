<?php
/**
 * Personalizaciones para Factura 80mm
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Evitar acceso directo

// Hook en 'init' para detectar parámetro en URL y activar una notificación temporal
add_action('init', function () {
    if (isset($_GET['confirmacion']) && $_GET['confirmacion'] === 'ok') {
        // Guardar un transient para mostrar notificación de QR escaneado (duración 60 segundos)
        set_transient('qr_show_notification', true, 60);
    }
});

// Agregar contenido y estilos en el footer de la web para mostrar notificación de escaneo QR
add_action('wp_footer', function () {
    // Mostrar solo a usuarios con rol 'storage_manager'
    if (!current_user_can('storage_manager')) return;
    ?>
    <style>
        /* Estilos para la notificación que aparece sobre toda la pantalla */
        #qr-scan-notification {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #000;
            padding: 1.5em 3em;
            font-size: 2rem;
            opacity: 0; /* Oculta inicialmente */
            pointer-events: none; /* No interactuable cuando oculto */
            transition: opacity 0.8s ease-in-out;
            z-index: 9999;
            text-align: center;
        }
        /* Mostrar notificación */
        #qr-scan-notification.show { opacity: 1; }
    </style>

    <div id="qr-scan-notification">Orden escaneada</div>
    <script>
        // Función asíncrona que consulta periódicamente al servidor si se escaneó un QR
        async function checkQRScan() {
            try {
                const response = await fetch('<?php echo admin_url("admin-ajax.php?action=check_qr_scan"); ?>');
                const result = await response.json();
                if (result.success && result.data.scanned) {
                    const notif = document.getElementById('qr-scan-notification');
                    notif.textContent = result.data.message;        // Mensaje dinámico
                    notif.style.backgroundColor = result.data.color; // Color según estado
                    notif.classList.add('show');                      // Mostrar notificación
                    setTimeout(() => notif.classList.remove('show'), 2500); // Ocultar tras 2.5s
                }
            } catch (e) {
                console.error('Error verificando escaneo QR', e);
            }
        }
        setInterval(checkQRScan, 3000); // Repetir cada 3 segundos
    </script>
    <?php
});

// Registro de la acción AJAX para comprobar si se escaneó el QR
add_action('wp_ajax_check_qr_scan', 'check_qr_scan_callback');
function check_qr_scan_callback() {
    // Validar permisos para acceder a esta acción
    if (!current_user_can('storage_manager')) {
        wp_send_json_error(['message' => 'No autorizado']);
    }
    // Obtener el transient que indica si se escaneó un QR
    $data = get_transient('qr_scan_active');
    if ($data && isset($data['scanned'])) {
        delete_transient('qr_scan_active'); // Borrar para que no se repita
        wp_send_json_success($data);         // Enviar resultado positivo
    } else {
        wp_send_json_success(['scanned' => false]); // No hay escaneo activo
    }
}

// Hook en 'template_redirect' para manejar acción QR vía URL (?action=qr_scan&order_id=...)
add_action('template_redirect', function () {
    // Validar que venga la acción y el ID del pedido
    if (isset($_GET['action']) && $_GET['action'] === 'qr_scan' && isset($_GET['order_id'])) {
        // Validar permisos: solo storage_manager puede hacer esto
        if (!current_user_can('storage_manager')) {
            wp_redirect(home_url('/')); // Redirigir si no autorizado
            exit;
        }

        $order_id = intval($_GET['order_id']);
        $order = wc_get_order($order_id);

        if ($order) {
            $status = $order->get_status();

            // Si el pedido está en estado 'impreso', lo actualiza a 'completed'
            if ($status === 'impreso') {
                $order->update_status('completed', 'Actualizado vía QR desde estado impreso');
                update_post_meta($order_id, '_qr_completed_at', current_time('mysql')); // Guardar fecha de actualización
                $order->add_order_note('La orden fue completada mediante escaneo de QR desde estado impreso.');
            }

            // Solo para ciertos estados muestra feedback visual
            if (in_array($status, ['impreso', 'processing', 'completed', 'on-hold', 'cancelled'])) {

                // Definir colores según estado para mostrar en notificación
                $color = match ($status) {
                    'on-hold'   => 'rgb(248, 221, 167)',
                    'processing'=> 'rgb(198, 225, 198)',
                    'impreso'   => 'rgb(251, 191, 18)',
                    'completed' => 'rgb(200, 215, 225)',
                    'cancelled' => 'rgb(229, 229, 229)',
                };

                // Mensajes amigables para cada estado
                $message = match ($status) {
                    'impreso'   => 'La orden fue escaneada',
                    'processing'=> 'La orden se esta procesando',
                    'completed' => 'La orden ya fue completada',
                    'on-hold'   => 'La orden esta en espera',
                    'cancelled' => 'La orden fue cancelada',
                };

                // Guardar transient con información para mostrar notificación en el front
                set_transient('qr_scan_active', [
                    'scanned' => true,
                    'color'   => $color,
                    'message' => $message,
                ], 60);
            }
        }

        // Redirigir al home tras procesar la acción
        wp_redirect(home_url('/'));
        exit;
    }
});

// Función para generar código QR en base64 usando servicio externo
function generate_base64_qr_code( $text = '', $size = 100 ) {
    $encoded_text = urlencode( $text );
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encoded_text}";

    $image_data = file_get_contents( $qr_url );
    if ( $image_data ) {
        // Retornar imagen en formato base64 para incrustar directamente
        return 'data:image/png;base64,' . base64_encode( $image_data );
    }

    return false; // Retornar falso si no se pudo generar
}

// 1. Asegurar que los precios muestren IVA incluido en facturas
add_filter( 'wpo_wcpdf_item_price', function( $price, $document, $item ) {
    if ( $document->get_type() == 'invoice' ) {
        // Calcular precio unitario total dividido por cantidad
        return wc_price( $item->get_total() / $item->get_quantity(), array('currency' => $document->order->get_currency()) );
    }
    return $price;
}, 10, 3 );

// 3. Estilos adicionales para impresión térmica y para evitar que el nombre de producto se corte mal
add_filter( 'wpo_wcpdf_template_styles', function( $css ) {
    return $css .= '
        .test-order-notice {
            color: red;
            font-weight: bold;
            text-align: center;
            margin: 2mm 0;
            border: 0.5mm dashed red;
            padding: 1mm;
        }
        .product-name {
            word-break: break-word;
        }
    ';
});

// 4. Validar cédula antes de mostrar en dirección de facturación
add_filter( 'wpo_wcpdf_billing_address', function( $address, $document ) {
    $cedula = $document->order->get_meta('_billing_cedula');
    if ( empty($cedula) || strlen($cedula) < 6 ) {
        // Si la cédula no existe o es muy corta, cambiar texto "CÉDULA:" por "IDENTIFICACIÓN:"
        $address = str_replace('CÉDULA:', 'IDENTIFICACIÓN:', $address);
    }
    return $address;
}, 10, 2 );
