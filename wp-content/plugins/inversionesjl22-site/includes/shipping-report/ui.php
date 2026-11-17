<?php
/**
 * Shipping report (Inversiones JL22 › Informe de Envíos): a form picks country, courier, dates and
 * shift; admin-post.php?action=shipping_report finds the completed orders and streams an 80 mm
 * PDF. Split in one file per WPCodeBox snippet, each moved as is.
 *
 * This file: the page with the form (its menu entry is in admin-menu.php), and its JS (assets/js/shipping-report.js, from
 * snippet 50, which WPCodeBox printed in the <head> of the admin pages whose URL contains
 * "shipping-report": enqueued in the same place). From Shipping Report › UI (snippet 53). Only
 * change: the form carries a nonce (checked in init.php). The form's styles
 * (assets/css/shipping-report-admin.css) came from the PDF plugin's custom/ folder.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_enqueue_scripts',
	static function () {
		if ( 'shipping-report' !== inversionesjl22_site_admin_screen() ) {
			return;
		}
		// Same handle PDF Invoices' custom/inversionesjl-pdf.php enqueued the copy it had with, and
		// before it (priority 5): while that plugin is active its call finds the handle taken and
		// the styles load once, from here.
		wp_enqueue_style(
			'jl-shipping-report-admin',
			INVERSIONESJL22_SITE_URL . 'assets/css/shipping-report-admin.css',
			array(),
			inversionesjl22_site_asset_version( 'assets/css/shipping-report-admin.css' )
		);
		wp_enqueue_script(
			'inversionesjl22-shipping-report',
			INVERSIONESJL22_SITE_URL . 'assets/js/shipping-report.js',
			array(),
			inversionesjl22_site_asset_version( 'assets/js/shipping-report.js' ),
			false
		);
	},
	5
);

// Su entrada del menú: admin-menu.php (Inversiones JL22 › Informe de Envíos, manage_woocommerce).

// Renderiza el formulario para seleccionar filtros
function shippingReportUI() {
    ?>
    <section>
        <h1>Informe de Envíos</h1>

        <form class="my-form" method="get" target="_blank" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="shipping_report">
            <?php wp_nonce_field('shipping_report', '_wpnonce', false); ?>

            <!-- País y Empresa de Envío -->
            <fieldset>
                <div>
                    <label for="country">País:</label>
                    <select name="country" id="country" required>
                        <option value="">Selecciona un país</option>
                        <?php
                        $zones = WC_Shipping_Zones::get_zones();
                        $countries = [];

                        foreach ($zones as $rawZone) {
                            $zone = new WC_Shipping_Zone($rawZone['zone_id']);
                            $zone_locations = $zone->get_zone_locations();

                            foreach ($zone_locations as $location) {
                                if ($location->type === 'country') {
                                    $code = $location->code;
                                    $name = WC()->countries->countries[$code] ?? $code;

                                    // Evitar duplicados si un país está en varias zonas
                                    if (!in_array($code, $countries, true)) {
                                        echo '<option value="' . esc_attr($code) . '">' . esc_html($name) . '</option>';
                                        $countries[] = $code;
                                    }
                                }
                            }
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label for="company">Empresa de envío:</label>
                    <?php // Filled by assets/js/shipping-report.js when a country is picked. ?>
                    <select name="company" id="company" required disabled>
                        <option value="">Elige primero un país</option>
                    </select>
                </div>
            </fieldset>

            <!-- Fechas -->
            <fieldset>
                <div>
                    <label for="startDate">Fecha desde:</label>
                    <input type="date" name="startDate" id="startDate" required>
                </div>
                <div>
                    <label for="endDate">Fecha hasta:</label>
                    <input type="date" name="endDate" id="endDate" required>
                </div>
                <div>
                    <label for="shift">Horario:</label>
                    <select name="shift" id="shift" required>
                        <option value="">Selecciona un horario</option>
                        <option value="morning">Matutino</option>
                        <option value="evening">Vespertino</option>
                        <option value="all_day">Todo el día</option>
                    </select>
                </div>
            </fieldset>

            <button type="submit" class="button button-primary">Generar PDF</button>
        </form>

        <script type="text/javascript">
            const ajaxUrl = "<?php echo admin_url('admin-ajax.php'); ?>";
            ajaxFetchShippingCompaniesJs(ajaxUrl);
        </script>
    </section>
    <?php
}
