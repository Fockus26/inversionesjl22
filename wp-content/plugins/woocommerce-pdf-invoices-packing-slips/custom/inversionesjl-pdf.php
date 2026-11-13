<?php
/**
 * Personalizaciones Inversiones JL para PDF Invoices & Packing Slips.
 *
 * - Tamaños de papel térmico 80mm y 58mm en "Display Settings > Paper size", con alto de hoja
 *   automático para que ninguna copia se parta entre hojas (los productos se perdían al imprimir).
 * - Ajustes "Tipografía" y "Grosor de la tipografía" en "Display Settings". Como son
 *   ajustes del formulario, la previsualización se actualiza al cambiarlos y, al guardar,
 *   se aplican también a los PDF reales. Vacío = lo que defina el style.css de la plantilla.
 *
 * Se mantiene en un archivo aparte para que al actualizar el plugin solo haya que
 * volver a copiar esta carpeta y la línea `include_once` en includes() del archivo principal.
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Evitar acceso directo

/**
 * Papel térmico: clave del ajuste => [ ancho, alto ] en puntos.
 * Mismas medidas que tenía el dompdf modificado de la versión anterior (CPDF::$PAPER_SIZES).
 * El alto solo se usa si se desactiva el alto automático (filtro jl_wcpdf_receipt_auto_height).
 */
function jl_wcpdf_receipt_papers() {
    return [
        '80mm' => [ 226.77, 792.00 ],
        '58mm' => [ 162.00, 792.00 ],
    ];
}

/**
 * Tipografías disponibles. Clave => [ etiqueta, font-family CSS, @font-face opcionales ].
 * Las fuentes del plugin (Open Sans, Roboto Slab, Segoe) y las de dompdf (DejaVu, Helvetica,
 * Times, Courier) ya están registradas; para otras se puede pasar 'font_face' con la ruta al TTF:
 *   'font_face' => [ [ 'weight' => 'normal', 'style' => 'normal', 'src' => 'https://.../MiFuente.ttf' ] ]
 */
function jl_wcpdf_fonts() {
    return apply_filters( 'jl_wcpdf_fonts', [
        'dejavu-sans-mono' => [ 'label' => 'DejaVu Sans Mono', 'family' => '"DejaVu Sans Mono", monospace' ],
        'dejavu-sans'      => [ 'label' => 'DejaVu Sans',      'family' => '"DejaVu Sans", sans-serif' ],
        'dejavu-serif'     => [ 'label' => 'DejaVu Serif',     'family' => '"DejaVu Serif", serif' ],
        'open-sans'        => [ 'label' => 'Open Sans',        'family' => '"Open Sans", sans-serif' ],
        'roboto-slab'      => [ 'label' => 'Roboto Slab',      'family' => '"Roboto Slab", serif' ],
        'segoe'            => [ 'label' => 'Segoe',            'family' => '"Segoe", sans-serif' ],
        'helvetica'        => [ 'label' => 'Helvetica',        'family' => 'Helvetica, sans-serif' ],
        'times'            => [ 'label' => 'Times',            'family' => 'Times, serif' ],
        'courier'          => [ 'label' => 'Courier',          'family' => 'Courier, monospace' ],
    ] );
}

/**
 * Grosores. dompdf solo dibuja las variantes normal y negrita de cada fuente,
 * por eso no se ofrecen 100-900.
 */
function jl_wcpdf_font_weights() {
    return [
        'normal' => 'Normal',
        'bold'   => 'Negrita',
    ];
}

// Opciones 80mm / 58mm en el selector de tamaño de papel (el valor '80mm' ya guardado de la versión anterior vuelve a quedar seleccionado)
add_filter( 'wpo_wcpdf_template_settings_paper_size', function ( $sizes ) {
    foreach ( array_keys( jl_wcpdf_receipt_papers() ) as $key ) {
        $sizes[ $key ] = $key;
    }
    return $sizes;
} );

// Convertir '80mm' / '58mm' a medidas que dompdf entiende
add_filter( 'wpo_wcpdf_paper_format', function ( $paper_size ) {
    $papers = jl_wcpdf_receipt_papers();
    // JL22: el tamaño del papel sale de WooCommerce > Documentos (plugin inversionesjl22-site),
    // no del ajuste "Paper size" de este plugin. Si el plugin propio no está, rige el ajuste.
    if ( function_exists( 'inversionesjl22_site_document_paper' ) ) {
        $paper_size = inversionesjl22_site_document_paper()['key'];
    }
    if ( ! is_string( $paper_size ) || ! isset( $papers[ $paper_size ] ) ) {
        return $paper_size;
    }
    return [ 0, 0, $papers[ $paper_size ][0], $papers[ $paper_size ][1] ];
} );

/*
 * Alto automático para papel térmico.
 *
 * Con alto fijo (792pt ≈ 279mm), un pedido con muchos productos no cabe en una hoja y la copia
 * se parte en dos. En pantalla se ve todo, pero al imprimir en rollo la impresora pierde lo que
 * queda en el corte entre hojas (márgenes no imprimibles, corte automático, tamaño de papel del
 * driver distinto al del PDF). Aquí se hace un primer render de prueba con una hoja muy alta,
 * se mide el contenido y se fija el alto de la hoja para que cada copia (cada page-break)
 * quepa completa en una sola hoja. Todas las hojas quedan con el alto de la copia más larga.
 */
add_filter( 'wpo_wcpdf_before_dompdf_render', function ( $dompdf, $html = '', $options = null ) {
    if ( ! apply_filters( 'jl_wcpdf_receipt_auto_height', true ) || empty( $html ) ) {
        return $dompdf;
    }

    $paper    = $dompdf->getPaperSize();
    $width_pt = (float) $paper[2];
    $is_receipt = false;
    foreach ( jl_wcpdf_receipt_papers() as $size ) {
        if ( abs( $width_pt - $size[0] ) < 0.5 ) {
            $is_receipt = true;
            break;
        }
    }
    if ( ! $is_receipt ) {
        return $dompdf; // A4 / Letter: sin cambios
    }

    try {
        $probe_height = 14000; // ≈ 4.9 m, más que cualquier pedido
        $dompdf_class = get_class( $dompdf );
        $probe        = new $dompdf_class( $options ?: $dompdf->getOptions() );
        $content_bottom = 0;

        $probe->setPaper( [ 0, 0, $width_pt, $probe_height ] );
        $probe->setCallbacks( [ [
            'event' => 'end_frame',
            'f'     => function ( $frame ) use ( &$content_bottom ) {
                $box = $frame->get_border_box();
                $content_bottom = max( $content_bottom, $box['y'] + $box['h'] );
            },
        ] ] );
        $probe->loadHtml( $html );
        $probe->render();
        unset( $probe );

        if ( $content_bottom > 0 && $content_bottom < $probe_height ) {
            // Margen inferior de @page (5mm en Factura80mm) + holgura para el corte de la impresora
            $bottom_space = (float) apply_filters( 'jl_wcpdf_receipt_bottom_space_pt', 10 * 72 / 25.4 );
            $min_height   = 50 * 72 / 25.4;
            $dompdf->setPaper( [ 0, 0, $width_pt, max( $min_height, ceil( $content_bottom + $bottom_space ) ) ] );
        }
    } catch ( \Throwable $e ) {
        // Si la medición falla se imprime con el alto fijo de siempre
        wcpdf_log_error( 'Alto automático 80mm: ' . $e->getMessage(), 'warning' );
    }

    return $dompdf;
}, 10, 3 );

// Campos "Tipografía" y "Grosor" justo después del selector de plantilla
add_filter( 'wpo_wcpdf_settings_fields_general', function ( $settings_fields, $page, $option_group, $option_name ) {
    $font_options = [ '' => 'Según la plantilla' ];
    foreach ( jl_wcpdf_fonts() as $key => $font ) {
        $font_options[ $key ] = $font['label'];
    }

    $new_fields = [
        [
            'type'     => 'setting',
            'id'       => 'jl_template_font',
            'title'    => 'Tipografía',
            'callback' => 'select',
            'section'  => 'general_settings',
            'args'     => [
                'option_name' => $option_name,
                'id'          => 'jl_template_font',
                'options'     => $font_options,
                'description' => 'Reemplaza la tipografía de la plantilla. La previsualización se actualiza al cambiarla; guarda para aplicarla a los PDF.',
            ],
        ],
        [
            'type'     => 'setting',
            'id'       => 'jl_template_font_weight',
            'title'    => 'Grosor de la tipografía',
            'callback' => 'select',
            'section'  => 'general_settings',
            'args'     => [
                'option_name' => $option_name,
                'id'          => 'jl_template_font_weight',
                'options'     => [ '' => 'Según la plantilla' ] + jl_wcpdf_font_weights(),
                'description' => 'Grosor del texto general. Los títulos marcados como negrita en la plantilla se mantienen.',
            ],
        ],
    ];

    $position = count( $settings_fields );
    foreach ( $settings_fields as $index => $field ) {
        if ( isset( $field['id'] ) && 'template_path' === $field['id'] ) {
            $position = $index + 1;
            break;
        }
    }
    array_splice( $settings_fields, $position, 0, $new_fields );

    return $settings_fields;
}, 10, 4 );

// Mostrar los campos dentro del grupo "Display Settings"
add_filter( 'wpo_wcpdf_general_settings_categories', function ( $categories ) {
    if ( isset( $categories['display']['members'] ) ) {
        $members  = $categories['display']['members'];
        $position = array_search( 'template_path', $members, true );
        $position = false === $position ? count( $members ) : $position + 1;
        array_splice( $members, $position, 0, [ 'jl_template_font', 'jl_template_font_weight' ] );
        $categories['display']['members'] = $members;
    }
    return $categories;
} );

// Aplicar tipografía y grosor (en la previsualización el plugin recarga los ajustes con los valores del formulario sin guardar)
add_action( 'wpo_wcpdf_custom_styles', function () {
    $settings = WPO_WCPDF()->settings->general_settings;
    $fonts    = jl_wcpdf_fonts();
    $weights  = jl_wcpdf_font_weights();
    $font_key = $settings['jl_template_font'] ?? '';
    $weight   = $settings['jl_template_font_weight'] ?? '';

    // JL22: la tipografía y el grosor salen de WooCommerce > Documentos (plugin inversionesjl22-site),
    // no de estos ajustes. Si el plugin propio no está, rigen los ajustes.
    if ( function_exists( 'inversionesjl22_site_document_text' ) ) {
        $text     = inversionesjl22_site_document_text();
        $font_key = $text['font'];
        $weight   = $text['font_weight'];
    }

    if ( isset( $fonts[ $font_key ] ) ) {
        $font = $fonts[ $font_key ];
        if ( ! empty( $font['font_face'] ) ) {
            foreach ( (array) $font['font_face'] as $face ) {
                printf(
                    '@font-face { font-family: "%1$s"; font-style: %2$s; font-weight: %3$s; src: url("%4$s"); }',
                    esc_attr( $font['label'] ),
                    esc_attr( $face['style'] ?? 'normal' ),
                    esc_attr( $face['weight'] ?? 'normal' ),
                    esc_url_raw( $face['src'] )
                );
            }
        }
        // Forzar la tipografía en todo el documento (la plantilla define su propia font-family)
        echo 'body, body * { font-family: ' . $font['family'] . ' !important; }'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- valor de la lista blanca
    }

    if ( isset( $weights[ $weight ] ) ) {
        // Solo en body: el texto lo hereda y las clases .bold de la plantilla siguen en negrita
        echo 'body { font-weight: ' . $weight . '; }'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- valor de la lista blanca
    }
}, 99 );

/*
 * JL22: la plantilla Factura80mm está escrita para 76 mm útiles. Con el papel de 58 mm
 * (WooCommerce > Documentos) se reajusta aquí, sin otra plantilla: cuerpo de 54 mm, letra algo
 * menor, datos del cliente con la etiqueta sobre el valor y la tabla de productos con más sitio
 * para el precio. Con 80 mm no se imprime nada.
 */
add_action( 'wpo_wcpdf_custom_styles', function () {
    if ( ! function_exists( 'inversionesjl22_site_document_paper' ) || 58 !== inversionesjl22_site_document_paper()['mm'] ) {
        return;
    }
    ?>
    @page { size: 58mm auto; margin: 2mm 2mm 5mm 2mm; }
    body { width: 54mm !important; font-size: 7.5pt; }
    .document-title { font-size: 9pt; }
    .heading { font-size: 1.3em; }
    .qr-code img { width: 18mm !important; height: 18mm !important; }
    .client-info-table td { display: block; width: 100%; padding: 0; }
    .client-info-table tr { display: block; margin-bottom: 1mm; }
    .items-table th, .items-table td { font-size: 7pt; }
    .product-name { width: 50%; font-size: 6.5pt; }
    .product-qty { width: 14%; padding: 0; }
    .product-price { width: 36%; }
    .product-sku { font-size: 6.5pt; }
    .variation-attributes, .attribute { font-size: 6.5pt; }
    .total-value { margin-left: 1mm; }
    .delivery-info-table { font-size: 7pt; }
    .delivery-info-table td, .delivery-info-table td:first-child { display: block; width: 100%; }
    .delivery-info-table tr { display: block; margin-bottom: 1mm; }
    .delivery-cut-message, .delivery-footer { font-size: 7pt; }
    <?php
}, 100 );

/**
 * Estilos del formulario "WooCommerce > Informe de Envíos" (página shipping-report, del snippet
 * "UI" de WPCodeBox). Solo se cargan en esa página.
 */
add_action( 'admin_enqueue_scripts', function ( $hook_suffix ) {
    if ( 'woocommerce_page_shipping-report' !== $hook_suffix ) {
        return;
    }

    $file = __DIR__ . '/shipping-report-admin.css';
    wp_enqueue_style( 'jl-shipping-report-admin', plugins_url( 'shipping-report-admin.css', __FILE__ ), [], filemtime( $file ) );
} );
