<?php
/**
 * =======================
 * INFORME DE ENVÍOS ADMIN
 * =======================
 * Añade un submenu para generar un reporte PDF
 * filtrado por país, empresa de envío y rango de fechas.
 */

// Agrega submenú en WooCommerce para el informe
add_action('admin_menu', function() {
    add_submenu_page(
        'woocommerce',
        'Informe de Envíos',
        'Informe de Envíos',
        'manage_woocommerce',
        'informe-envios',
        'mostrar_formulario_informe_envios',
    );
});

// Renderiza el formulario para seleccionar filtros
function mostrar_formulario_informe_envios() {
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Informe de Envíos</h1>
        <hr class="wp-header-end">
        <style>
            /* Estilos para el formulario */
            .form-row {
                display: flex;
                gap: 20px;
                margin-bottom: 20px;
            }
            .form-group {
                display: flex;
                flex-direction: column;
            }
            .form-group select,
            .form-group input[type="date"] {
                height: 34px;
                padding: 5px 10px;
                font-size: 14px;
            }
            #pais { min-width: 150px; }
            #shipping_company { width: 350px; }
        </style>

        <form method="get" target="_blank" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="generar_informe_envio">

            <!-- Fila 1: Selector País y Empresa de Envío -->
            <div class="form-row">
                <div class="form-group">
                    <label for="pais">País:</label>
                    <select name="pais" id="pais" required>
                        <option value="">Selecciona un país</option>
                        <option value="VE">Venezuela</option>
                        <option value="CO">Colombia</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="shipping_company">Empresa de envío:</label>
                    <select name="shipping_company" id="shipping_company" required>
                        <option value="all">Todo el país</option>
                        <!-- Opciones cargadas vía AJAX según país -->
                    </select>
                </div>
            </div>

            <!-- Fila 2: Fechas inicio y fin -->
            <div class="form-row">
                <div class="form-group" style="width: 150px;">
                    <label for="fecha_inicio">Fecha desde:</label>
                    <input type="date" name="fecha_inicio" id="fecha_inicio" required>
                </div>
                <div class="form-group" style="width: 150px;">
                    <label for="fecha_fin">Fecha hasta:</label>
                    <input type="date" name="fecha_fin" id="fecha_fin" required>
                </div>
            </div>

            <button type="submit" class="button button-primary">Generar PDF</button>
        </form>

        <script type="text/javascript">
            const ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
        </script>

        <script type="text/javascript">
            // Carga dinámica de empresas de envío según país seleccionado
            document.addEventListener('DOMContentLoaded', function() {
                const paisSelect = document.getElementById('pais');
                const empresaSelect = document.getElementById('shipping_company');

                paisSelect.addEventListener('change', function() {
                    const pais = this.value;
                    empresaSelect.innerHTML = '<option value="all">Todo el país</option>';
                    if (!pais) return;

                    fetch(ajaxurl + '?action=obtener_empresas_envio&pais=' + pais)
                        .then(res => res.json())
                        .then(({ success, data }) => {
                            if (success && data.empresas.length > 0) {
                                data.empresas.forEach(empresa => {
                                    const option = document.createElement('option');
                                    option.value = empresa;
                                    option.textContent = empresa;
                                    empresaSelect.appendChild(option);
                                });
                            }
                        });
                });
            });
        </script>
    </div>
    <?php
}

// Obtiene empresas de envío usadas (sin filtro país) - no usado actualmente
function obtener_metodos_envio_usados() {
    global $wpdb;
    $resultados = $wpdb->get_col("
        SELECT DISTINCT meta_value 
        FROM {$wpdb->prefix}postmeta 
        WHERE meta_key = '_shipping_company'
        AND meta_value IS NOT NULL
        AND meta_value != ''
    ");
    return array_filter($resultados);
}

// Obtiene empresas de envío para un país específico (sin filtro extra)
function obtener_metodos_envio_por_pais($pais) {
    global $wpdb;
    $resultados = $wpdb->get_col($wpdb->prepare("
        SELECT DISTINCT pm1.meta_value
        FROM {$wpdb->prefix}postmeta AS pm1
        INNER JOIN {$wpdb->prefix}postmeta AS pm2 ON pm1.post_id = pm2.post_id
        WHERE pm1.meta_key = '_shipping_company'
        AND pm2.meta_key = '_shipping_country'
        AND pm2.meta_value = %s
    ", $pais));
    return array_filter($resultados);
}

// Traduce código de país a nombre legible
function obtener_nombre_pais($codigo) {
    $nombres = [
        'VE' => 'Venezuela',
        'CO' => 'Colombia',
    ];
    return $nombres[$codigo] ?? $codigo;
}

// Lista fija de empresas permitidas por país (para filtrar la consulta)
function obtener_empresas_definidas_por_pais($pais) {
    $empresas = [
        'VE' => [
            'TEALCA',
            'MRW',
            'DOMESA',
            'ZOOM',
            'DOMICILIO "San Cristobal"',
        ],
        'CO' => [
            'ENVIA',
            'INTERRAPIDISIMO',
            'DOMICILIO "Cúcuta"',
            'CONTRA-ENTREGA ( Efectivo)',
        ]
    ];
    return $empresas[$pais] ?? [];
}

// AJAX: Devuelve empresas de envío válidas para un país, filtradas por las definidas
add_action('wp_ajax_obtener_empresas_envio', 'ajax_obtener_empresas_envio');
function ajax_obtener_empresas_envio() {
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => 'No autorizado']);
    }

    $pais = sanitize_text_field($_GET['pais']);
    if (empty($pais)) {
        wp_send_json_error(['message' => 'País vacío']);
    }

    $empresas_validas = obtener_empresas_definidas_por_pais($pais);
    if (empty($empresas_validas)) {
        wp_send_json_success(['empresas' => []]);
    }

    global $wpdb;
    $placeholders = implode(',', array_fill(0, count($empresas_validas), '%s'));

    $query = "
        SELECT DISTINCT pm1.meta_value
        FROM {$wpdb->prefix}postmeta AS pm1
        INNER JOIN {$wpdb->prefix}postmeta AS pm2 ON pm1.post_id = pm2.post_id
        WHERE pm1.meta_key = '_shipping_company'
        AND pm2.meta_key = '_shipping_country'
        AND pm2.meta_value = %s
        AND pm1.meta_value IN ($placeholders)
    ";

    $prepared = $wpdb->prepare($query, array_merge([$pais], $empresas_validas));
    $empresas = $wpdb->get_col($prepared);

    wp_send_json_success(['empresas' => array_unique($empresas)]);
}

// Procesa la solicitud del informe, filtra pedidos y dispara generación de PDF
add_action('admin_post_generar_informe_envio', 'procesar_informe_envio');
function procesar_informe_envio() {
    if (!current_user_can('manage_woocommerce')) wp_die('No autorizado');

    $pais = sanitize_text_field($_GET['pais']);
    $empresa = sanitize_text_field($_GET['shipping_company']);
    $fecha_inicio = sanitize_text_field($_GET['fecha_inicio']);
    $fecha_fin = sanitize_text_field($_GET['fecha_fin']);

    $meta_query = [
        [
            'key'   => '_shipping_country',
            'value' => $pais,
            'compare' => '='
        ]
    ];

    if ($empresa !== 'all') {
        // Filtrar por empresa específica
        $meta_query[] = [
            'key'   => '_shipping_company',
            'value' => $empresa,
            'compare' => '='
        ];
    } else {
        // Filtrar por todas las empresas válidas para el país
        $empresas_validas = obtener_empresas_definidas_por_pais($pais);
        if (!empty($empresas_validas)) {
            $meta_query[] = [
                'key'     => '_shipping_company',
                'value'   => $empresas_validas,
                'compare' => 'IN',
            ];
        }
    }

    // Query para obtener pedidos según filtros
    $query = new WP_Query([
        'post_type' => 'shop_order',
        'post_status' => ['wc-completed', 'wc-processing'],
        'posts_per_page' => -1,
        'date_query' => [
            [
                'after'     => $fecha_inicio,
                'before'    => $fecha_fin,
                'inclusive' => true,
            ],
        ],
        'meta_query' => $meta_query,
    ]);

    $pedidos = $query->posts;
    $empresa_legible = ($empresa === 'all') ? obtener_nombre_pais($pais) : $empresa;

    // Dispara la acción que genera el PDF con los datos del informe
    do_action('wpo_wcpdf_custom_report_pdf', [
        'empresa_nombre' => $empresa_legible,
        'fecha_inicio'   => $fecha_inicio,
        'fecha_fin'      => $fecha_fin,
        'pedidos'        => $pedidos,
    ]);

    exit;
}
