# fix/roles-preview-url-status (2026-11-11)

Hallazgos de la prueba de roles en staging (código cerrado, igual que WPCodeBox / producción):

- Vista previa del pedido sin filtro de rol: `filterPreviewActionsOrderStatuses` miraba el global
  `$post`, vacío en el AJAX `woocommerce_get_order_details` → acciones de Woo para todos
  (storage: "Completado" en Procesando). Ahora usa `$order` y `isStatusAllowedForAction`.
- Estados ocultos por URL: `post_status => ['do-not-match']` lo ignora WP_Query → lista sin filtro
  (storage veía en espera y papelera; seo podía borrar definitivamente). Ahora `post__in [0]`.
- `authorizeOrderView`: `empty($ruleset[$status])` (papelera/pendiente sin regla daban aviso PHP).

Verificado en staging: seo en navegador (lista, papelera, vista previa); storage con wp-cli
(Cloudflare puso desafío anti-bot al navegador). Admin sin cambios.
No arreglado (config, no código): URE Pro "Admin menu access" manda a storage al Escritorio tras
cada acción rápida (URL con parámetros fuera de su lista). Pendiente: las acciones AJAX
orderTo*/woocommerce_mark_order_status no validan el rol en servidor.
