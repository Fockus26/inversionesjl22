# perf/plugin-assets (2026-11-11)

- Cargador sin el puente de WPCodeBox (marcadores por módulo + ReflectionFunction sobre los
  hooks en cada carga). Queda una sola guarda: si `paymentStatusUI` ya existe (WPCodeBox
  reactivado), no carga ningún módulo y avisa en wp-admin, en vez de un error fatal.
- Assets por pantalla (`inversionesjl22_site_admin_screen()`: order / orders / shipping-report,
  posts y HPOS): admin-form.css y payment-status.js ya no van en todo wp-admin; qr-scan.js solo
  para storage logueado (antes, todo visitante del front).
- customer-status.php: script y estilo en línea de todo wp-admin → assets/js/customer-status.js
  (nonce por wp_localize_script) + admin-form.css, solo en la lista.
- roles-actions.php: setInterval 500 ms perpetuo → MutationObserver sobre #posts-filter.

Verificado en la copia (.qa/assets-por-pantalla.js, .qa/pago-ficha.js): escritorio sin assets
del plugin; lista con admin-form.css + customer-status.js; informe con su JS y empresas por país;
front anónimo solo site.css; storage sin wuoc_combine; vista previa guarda el status (200); sin
errores JS.
