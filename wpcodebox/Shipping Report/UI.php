<?php 
// Agrega submenú en WooCommerce para el informe
add_action('admin_menu', function() {
    add_submenu_page(
        'woocommerce',
        'Informe de Envíos',
        'Informe de Envíos',
        'manage_woocommerce',
        'shipping-report',
        'shippingReportUI'
    );
});

// Renderiza el formulario para seleccionar filtros
function shippingReportUI() {
    ?>
    <section>
        <h1>Informe de Envíos</h1>

        <form class="my-form" method="get" target="_blank" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="shipping_report">

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
                    <select name="company" id="company" required></select>
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
                    <label for="shift">Horario</label>
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
