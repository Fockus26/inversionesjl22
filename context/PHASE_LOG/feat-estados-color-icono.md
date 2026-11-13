# feat/estados-color-icono — Fase 3, unidad 2b: color e icono por estado

**Qué:** cada estado puede llevar un color (paleta cerrada de 9 tonos, todos ≥ 6.4:1) y un icono
(20 Dashicons) elegidos en WooCommerce › Estados y flujo. El color pinta la insignia del estado
en la lista de pedidos; el icono, su acción rápida. El CSS se genera de la configuración
(`inversionesjl22_site_status_css()`), así que `assets/css/admin-orders.css` desaparece: sus 4
glifos (En espera, Reservar, Impreso, Cancelar) son ahora los iconos por defecto.
Rama sobre feat/pantalla-estados-flujo (#34).

**Por defecto:** sin color (el de WooCommerce) y los mismos 4 iconos de antes.

**Fuera:** el color de la píldora del QR (`qr-scan.php`, mapa propio) y los correos.
