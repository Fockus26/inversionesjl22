<?php
/**
 * =======================
 * INFORME DE ENVÍOS ADMIN
 * =======================
 * Añade un submenu para generar un reporte PDF
 * filtrado por país, empresa de envío y rango de fechas.
 */

// Helper para saber si el usuario actual tiene uno de los roles permitidos
function jl22_usuario_puede_ver_informe() {
    if (!is_user_logged_in()) return false;

    $usuario = wp_get_current_user();
    $roles_permitidos = ['administrator', 'storage_manager', 'seo_manager'];

    return array_intersect($roles_permitidos, (array) $usuario->roles);
}

// Agrega submenú en WooCommerce para el informe
add_action('admin_menu', function() {
    add_submenu_page(
        'woocommerce',
        'Informe de Envíos',
        'Informe de Envíos',
        'read',
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
            #pais { min-width: 200px; }
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
                        <?php
                        $zonas = WC_Shipping_Zones::get_zones();
                        $paises_agregados = [];

                        foreach ($zonas as $zona) {
                            $zone = new WC_Shipping_Zone($zona['zone_id']);
                            $zone_locations = $zone->get_zone_locations();

                            foreach ($zone_locations as $loc) {
                                if ($loc->type === 'country') {
                                    $codigo = $loc->code;
                                    $nombre = WC()->countries->countries[$codigo] ?? $codigo;

                                    // Evitar duplicados si un país está en varias zonas
                                    if (!in_array($codigo, $paises_agregados, true)) {
                                        echo '<option value="' . esc_attr($codigo) . '">' . esc_html($nombre) . '</option>';
                                        $paises_agregados[] = $codigo;
                                    }
                                }
                            }
                        }
                        ?>
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

// Traduce código de país a nombre legible
function obtener_nombre_pais($codigo) {
    $nombres = [
        'VE' => 'Venezuela',
        'CO' => 'Colombia',
    ];
    return $nombres[$codigo] ?? $codigo;
}

function obtener_empresas_definidas_por_pais($pais) {
    $empresas = [];

    // Obtenemos todas las zonas de envío
    $zonas = WC_Shipping_Zones::get_zones();

    foreach ($zonas as $zona) {
        $zone = new WC_Shipping_Zone($zona['zone_id']);
        $zone_locations = $zone->get_zone_locations();

        // Revisamos si esta zona aplica al país dado
        foreach ($zone_locations as $loc) {
            if ($loc->type === 'country' && $loc->code === $pais) {
                // Obtener métodos activos de la zona
                $metodos = $zone->get_shipping_methods(true, 'values');
                foreach ($metodos as $method) {
                    if ($method->enabled === 'yes') {
                        $empresas[] = $method->get_title(); // nombre visible en el checkout
                    }
                }
            }
        }
    }

    return $empresas;
}

// AJAX: Devuelve empresas de envío válidas para un país, filtradas por las definidas
add_action('wp_ajax_obtener_empresas_envio', 'ajax_obtener_empresas_envio');
function ajax_obtener_empresas_envio() {
    if (!jl22_usuario_puede_ver_informe()) {
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

    $query = $wpdb->prepare("
        SELECT DISTINCT oi.order_item_name
        FROM {$wpdb->prefix}woocommerce_order_items oi
        INNER JOIN {$wpdb->prefix}posts p 
            ON oi.order_id = p.ID
        INNER JOIN {$wpdb->prefix}postmeta pm_country 
            ON pm_country.post_id = p.ID
               AND pm_country.meta_key = '_shipping_country'
        WHERE oi.order_item_type = 'shipping'
          AND pm_country.meta_value = %s
          AND p.post_type = 'shop_order'
          AND p.post_status IN ('wc-processing','wc-completed','wc-impreso')
    ", $pais);

    $empresas_db = $wpdb->get_col($query);

    $empresas = [];
    foreach ($empresas_db as $empresa_db) {
        foreach ($empresas_validas as $valida) {
            if (strcasecmp($empresa_db, $valida) === 0) {
                $empresas[] = $valida;
                break;
            }
        }
    }

    wp_send_json_success(['empresas' => array_unique($empresas)]);
}

// Reemplaza la función procesar_informe_envio completa por esta versión y filtrado robusto
add_action('admin_post_generar_informe_envio', 'procesar_informe_envio');
function procesar_informe_envio() {
    if (!jl22_usuario_puede_ver_informe()) wp_die('No autorizado');

    global $wpdb;

    $pais         = isset($_GET['pais']) ? sanitize_text_field($_GET['pais']) : '';
    $empresa      = isset($_GET['shipping_company']) ? sanitize_text_field($_GET['shipping_company']) : 'all';
    $fecha_inicio = isset($_GET['fecha_inicio']) ? sanitize_text_field($_GET['fecha_inicio']) : '';
    $fecha_fin    = isset($_GET['fecha_fin']) ? sanitize_text_field($_GET['fecha_fin']) : '';

    // --- Normalizar empresa recibida desde GET ---
    $empresa_filtro_sql = "";
    $params = [];

    if ($empresa !== 'all') {
        // Normalizamos el valor recibido (PHP-side)
        $empresa_param = stripslashes($empresa); // quita backslashes si vienen escapados
        $empresa_param = trim($empresa_param);
        // reemplaza comillas curvas por comilla recta
        $empresa_param = str_replace(['“','”','`','´'], '"', $empresa_param);
        // remover duplicados espacios
        $empresa_param = preg_replace('/\s+/', ' ', $empresa_param);

        $empresa_filtro_sql = "AND TRIM(
            REPLACE(
                REPLACE(
                    REPLACE(oi.order_item_name, '“', '\"'),
                    '”', '\"'
                ),
                '\\\\\"', '\"'
            )
        ) = %s";

        $params[] = $empresa_param;
    } else {
        // mantener comportamiento 'all' original
        $empresas_validas = obtener_empresas_definidas_por_pais($pais);
        if (!empty($empresas_validas)) {
            $placeholders = implode(',', array_fill(0, count($empresas_validas), '%s'));
            // Normalizar los valores que pasamos como parámetros
            $empresas_normalizadas = array_map(function($e) {
                $e = stripslashes($e);
                $e = str_replace(['“','”','`','´'], '"', $e);
                return trim($e);
            }, $empresas_validas);

            $empresa_filtro_sql = "AND TRIM(
                REPLACE(
                    REPLACE(
                        REPLACE(oi.order_item_name, '“', '\"'),
                        '”', '\"'
                    ),
                    '\\\\\"', '\"'
                )
            ) IN ($placeholders)";
            $params = array_merge($params, $empresas_normalizadas);
        }
    }

    // Filtro de país y fechas
    $params = array_merge([$pais, $fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59'], $params);

    // Query para obtener IDs de pedidos (casteando completed_date)
    $sql = "
      SELECT DISTINCT p.ID
      FROM {$wpdb->prefix}posts p
      INNER JOIN {$wpdb->prefix}postmeta pm_country
          ON p.ID = pm_country.post_id
          AND pm_country.meta_key = '_shipping_country'
          AND pm_country.meta_value = %s
      INNER JOIN {$wpdb->prefix}postmeta pm_completed
          ON p.ID = pm_completed.post_id
          AND pm_completed.meta_key = '_completed_date'
      INNER JOIN {$wpdb->prefix}woocommerce_order_items oi
          ON p.ID = oi.order_id
          AND oi.order_item_type = 'shipping'
      WHERE p.post_type = 'shop_order'
        AND p.post_status = 'wc-completed'
        AND CAST(pm_completed.meta_value AS DATETIME) BETWEEN %s AND %s
        $empresa_filtro_sql
      ORDER BY pm_completed.meta_value DESC
    ";

    $pedidos_ids = $wpdb->get_col($wpdb->prepare($sql, ...$params));

    // helper para normalizar strings: quita tildes, minusculas, trim y remueve comillas curvas/rectas
    if (!function_exists('jl22_normalize')) {
        function jl22_normalize($s) {
            if (function_exists('remove_accents')) {
                $s = remove_accents($s);
            } else {
                $s = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
            }
            $s = strtolower(trim($s));
            $s = str_replace(['"', "'", '“','”','`','´'], '', $s);
            $s = preg_replace('/\s+/', ' ', $s);
            return $s;
        }
    }

    $empresa_for_matching = stripslashes($empresa);
    $needle_normal = jl22_normalize($empresa_for_matching);

    // Obtener objetos de pedido y filtrar por coincidencia robusta
    $pedidos = [];
    foreach ($pedidos_ids as $pid) {
        $order = wc_get_order($pid);
        if (!$order) continue;

        $matches = false;

        $ship_items = $order->get_items('shipping');
        foreach ($ship_items as $ship_item) {
            $method_name = (string) $ship_item->get_name();
            $normalized_method = jl22_normalize($method_name);

            if ($empresa !== 'all') {
                if ($needle_normal !== '' && (strpos($normalized_method, $needle_normal) !== false || $normalized_method === $needle_normal)) {
                    $matches = true;
                    break;
                }
            } else {
                $matches = true;
                break;
            }
        }

        // fallback: comparar con meta _shipping_company si existe
        if (!$matches) {
            $meta_company = (string) get_post_meta($pid, '_shipping_company', true);
            if ($meta_company !== '' && strpos(jl22_normalize($meta_company), $needle_normal) !== false) {
                $matches = true;
            }
        }

        if ($matches) {
            $pedidos[] = $order;
        }
    }

    // Convertir el código de país a nombre legible
    $pais_nombre = obtener_nombre_pais($pais);

    // Si es "all", mostrar "Todo el país", no el nombre del país
    $empresa_legible = ($empresa === 'all') ? 'Todo el país' : $empresa;

    // Generar el PDF con la acción existente
    do_action('wpo_wcpdf_custom_report_pdf', [
        'empresa_nombre' => $empresa_legible,
        'fecha_inicio'   => $fecha_inicio,
        'fecha_fin'      => $fecha_fin,
        'pedidos'        => $pedidos,
        'pais'           => $pais_nombre
    ]);


    exit;
}
