# Adopción — inventario (paso 4A de `wordpress-adopt.md`)

> Hecho el 2026-11-09 sobre la copia local (base de producción del 2026-11-08). Nada se cambió
> en producción. Estado: `pendiente` · `migrado` (en el plugin, probado en la copia) · `n/a`.

## 1. Snippets de WPCodeBox 2 (24, todos activos, sin errores)

Se ejecutan desde **`wpcodebox_functionality_plugin`** (plugin generado por WPCodeBox, activo,
incluye los 24), no desde la base. El código es el mismo en las tres fuentes (repo `wpcodebox/`,
tabla `wpcb_snippets`, plugin generado). Única diferencia: en la base los emojis de los
comentarios quedaron como `?` (tablas sin utf8mb4), así que **la fuente para migrar es `wpcodebox/`**.

| # | Orden | Snippet (id) | Tipo · dónde corre | Va a | Estado |
|---|---|---|---|---|---|
| 1 | 1 | Utils › Format Date Range (59) | PHP | `includes/helpers.php` | migrado (PR #5) |
| 2 | 1 | Utils › Get Pick Up Shipping Methods (60) | PHP | `includes/helpers.php` | migrado (PR #5) |
| 3 | 2 | Roles › Config (56) | PHP | `includes/roles.php` | migrado (PR #6) |
| 4 | 2 | Roles › Init Globally (52) | PHP | `includes/roles.php` | migrado (PR #6) |
| 5 | 2 | Roles › Init (54) | PHP (14 KB) | `includes/roles.php` | migrado (PR #6) |
| 6 | 3 | Order › Custom Status (49) | PHP | `includes/order-statuses.php` | migrado (PR #7) |
| 7 | 3 | User › Custom Status (64) | PHP | `includes/customer-status.php` | migrado (PR #7) |
| 8 | 4 | Order › Status Transition (47) | PHP | `includes/order-transitions.php` | migrado (PR #8) |
| 9 | 4 | Order › Render Feedback Status Transition (45) | PHP | `includes/order-transition-notices.php` | migrado (PR #8) |
| 10 | 5 | Order › Payment Status (43) | PHP | `includes/payment-status.php` | migrado (PR #9) |
| 11 | 5 | Order › Payment Status_1 (46) | JS · admin header | `assets/js/payment-status.js` | migrado (PR #9) |
| 12 | 6 | Order › Change Shipping Method (48) | PHP | `includes/change-shipping-method.php` | migrado (PR #10) |
| 13 | 6 | Order › Combined Orders (44) | PHP | `includes/combined-orders.php` | migrado (PR #10) |
| 14 | 7 | PDF › Scan Qr (58) | PHP (+ filtros IVA y cédula del PDF) | `includes/qr-scan.php` | migrado (PR #11) |
| 15 | 7 | PDF › Scan Qr_1 (62) | JS · frontend header | `assets/js/qr-scan.js` | migrado (PR #11) |
| 16 | 8 | Shipping Report › Init_1 (61) | PHP | `includes/shipping-report/init.php` | migrado (PR #12) |
| 17 | 8 | Shipping Report › UI (53) | PHP | `includes/shipping-report/ui.php` | migrado (PR #12) |
| 18 | 8 | Shipping Report › Fetch_1 (55) | PHP | `includes/shipping-report/fetch.php` | migrado (PR #12) |
| 19 | 8 | Shipping Report › Fetch (50) | JS · admin header | `assets/js/shipping-report.js` | migrado (PR #12) |
| 20 | 8 | Shipping Report › PDF UI (51) | PHP | `includes/shipping-report/pdf-ui.php` | migrado (PR #12) |
| 21 | 8 | Shipping Report › PDF (57) | PHP | `includes/shipping-report/pdf.php` | migrado (PR #12) |
| 22 | 9 | Boton Limpiar Carrito (42) | PHP | `includes/clear-cart.php` | migrado (PR #13) |
| 23 | 9 | Styles › Menu (63) | SCSS · frontend | `assets/scss/_menu.scss` → `site.css` | migrado (PR #13) |
| 24 | 9 | Form (65) | CSS · admin header | `assets/css/admin-form.css` | migrado (PR #13) |

"Orden" = el orden sugerido para migrar (helpers y roles primero: los demás los usan). Un
snippet por commit; al moverlo, se desactiva **ese** snippet en la copia y se prueba su flujo
con las cuentas de storage_manager y seo_manager.

## 2. Otro código que vive fuera del repo

| Qué | Dónde | Va a | Estado |
|---|---|---|---|
| `disable-turnstile-woocommerce-ajax.php` (apaga Turnstile en AJAX) | mu-plugin en producción, no está en el repo | **no se migra (2026-11-09): no hace nada.** Usa la opción `cfturnstile_enable`, el script `cf-turnstile` y la acción `cf_turnstile_verify`, que no existen en Simple Cloudflare Turnstile 1.44.5; solo imprime un espacio antes de las cabeceras. **César lo renombró a `.off` en producción el 2026-11-09** (respaldo descargado) | n/a — apagado |
| ACF (grupos, CPT, taxonomías) | — | no hay ninguno | n/a |
| Elementor Custom Code | — | no hay ninguno | n/a |

## 3. Tema hijo `woodmart-child` — **inactivo en producción**

- Producción usa el tema padre `woodmart` 7.2.5 (`template` = `stylesheet` = `woodmart`). Existen
  `theme_mods_woodmart-child`, así que estuvo activo alguna vez.
- Su `inc/` repite la lógica de los snippets (estados, transiciones, envíos, roles) y además
  trae cosas que **hoy no corren en producción**: `sku-generator.php`, `yith.php`,
  `enqueue.php`, `admin-notices.php`, `open-graph.php`.
- El repo y la copia de producción del tema difieren en 5 archivos, y el repo pide
  `inc/roles/order-visibility.php` pero el archivo se llama `order-visibliity.php`: activarlo
  así daría un error fatal.
- **Decisión de César (2026-11-09): migrar al plugin** lo que solo vive en el tema hijo. El tema
  hijo se queda inactivo (no activarlo). Revisado archivo por archivo:

| Archivo del tema hijo | Qué hace | Va a | Estado |
|---|---|---|---|
| `sku-generator.php` | SKU automático por categoría al guardar un producto sin SKU | `includes/sku-generator.php` | migrado (PR #14) — **nuevo en producción** (hoy no corre) |
| `open-graph.php` | og:title/description/image/url en la portada | Yoast ya pone og:title, og:url y og:type: solo falta la **imagen**. Va como filtro de Yoast (`wpseo_add_opengraph_images`) con `Preview.png` en `assets/images/`, sin duplicar etiquetas | migrado (PR #16) — nuevo en producción |
| `admin-notices.php` | avisos por `?jl22_notice=` | nada en los snippets ni en el plugin produce ese parámetro: código muerto | n/a |
| `yith.php` | código de barras de YITH Barcodes en el pedido | el plugin YITH Barcodes no está instalado y ninguna plantilla llama al hook: código muerto | n/a |
| `enqueue.php` | carga el `style.css` del tema hijo | el `style.css` solo tiene la cabecera del tema | n/a |
| `inc/order/*`, `inc/report/*`, `inc/roles/*` | versiones viejas de los snippets | los snippets de §1 (la versión que corre) | n/a |

## 4. Plugins (`wp plugin list`, producción)

- **Premium** (por eso la copia es Docker): Woodmart + woodmart-core, WPBakery (`js_composer`),
  Slider Revolution, WPCodeBox 2, User Role Editor Pro, WPC Product Bundles Premium, Easy Sale
  Badges Pro, XT Floating Cart.
- **Con actualización pendiente:** woocommerce-ajax-cart, filebird, jetpack, litespeed-cache,
  email-template-customizer-for-woo, oneclick-whatsapp-order, easy-sale-badges, instagram-feed,
  woocommerce-services. Woodmart 7.2.5 está muy atrás de la versión actual.
- **Inactivos:** akismet, filebird, hello, jetpack, yith-maintenance-mode, **yith-activator**,
  temas sarcom y twentytwenty*.
- ⚠️ **`yith-activator`** (autor "Phukit|Babiato"): falsifica las respuestas del servidor de
  licencias de YITH. Es un plugin pirata; inactivo, pero está instalado. Señalado, no borrado.
- Activos en producción y apagados **solo en la copia**: wp-mail-smtp, litespeed-cache,
  simple-cloudflare-turnstile, facebook-for-woocommerce, google-listings-and-ads, google-site-kit.

## 5. Cron de plugins que ya no están

`blc_*` (Broken Link Checker), `puc_cron_check_updates-hostinger`, `siteground_optimizer_*`,
`wp_statistics_*`, `wpmudev_scheduled_jobs`, `wpo_weekly_cron_tasks`, `sbc_about_us_update`,
`wcct_*`, `jetpack_*`/`jp_*` (Jetpack inactivo), `objectcache_*`. Se limpian con una migración
del plugin cuando se decida (no a mano en el admin). **Hecho: migración 002, PR #20** (César, 2026-11-09).
`sbc_about_us_update` (Instagram Feed) y `jetpack_*` (jetpack-connection de WooCommerce) siguen vivos: no se tocan.

## 6. Unidades (una rama y un PR cada una, después de mergear #4)

| U | Rama | Trae | Notas |
|---|---|---|---|
| 1 | `feat/site-helpers` | 59, 60 | base de los demás |
| 2 | `feat/site-roles` | 56, 52, 54 | probar con storage_manager y seo_manager |
| 3 | `feat/site-order-statuses` | 49, 64 | Printed, Reserved |
| 4 | `feat/site-order-transitions` | 47, 45 | |
| 5 | `feat/site-payment-status` | 43, 46 | |
| 6 | `feat/site-order-editing` | 48, 44 | cambio de envío, pedidos combinados |
| 7 | `feat/site-qr-scan` | 58, 62 | flujo de bodega (ojo caché de Cloudflare) |
| 8 | `feat/site-shipping-report` | 61, 53, 55, 50, 51, 57 | |
| 9 | `feat/site-cart-and-styles` | 42, 63, 65 | |
| 10 | `feat/site-turnstile-ajax` | mu-plugin | |
| 11 | `feat/site-sku-generator` | tema hijo | nuevo comportamiento en producción · PR #14 |
| 12 | `feat/site-og-image` | tema hijo | vía Yoast · PR #16 · og:description de la portada: vacía (César, 2026-11-09) |
| 13 | `fix/site-payment-status-a11y` | hallazgo de U5 | label de Moneda apunta a otro campo y Monto sin label (WCAG 1.3.1, 4.1.2) · PR #17 |
| 15 | `chore/site-cleanup` | decisiones de César | cron huérfano (002) y borrar yith-activator (003) · PR #20 |
| 14 | `fix/site-snippet-findings` | hallazgos de U6 y U8 | nonce de Limpiar carrito; `$transient_key` sin definir y `'wc-impreso'` en el AJAX del informe; `ob_get_clean()` sin `ob_start()` en el PDF; nonce del informe; `reset()` de una llamada en combinados; SKU: redeclaración fatal si se activa el tema hijo (U11); **PDF del informe roto: `DompdfDompdf` no existe en PDF Invoices 5.16.6** · PR #18 |

**Sin doble ejecución mientras tanto (hecho en U1):** el plugin carga cada módulo migrado al
final de `plugins_loaded` y solo si su función marcadora no existe (la declaró WPCodeBox). Cada
unidad añade su línea en el cargador de `inversionesjl22-site.php`, **en el orden de
`wpcodebox_functionality_plugin/plugin.php`** (hooks de igual prioridad: los roles filtran lo que
agregan los estados). Mientras convivan, un módulo migrado corre después de todo lo que siga en
WPCodeBox: migrar en el orden del plan (roles antes que lo que dependa de ellos).

**Prueba de cada unidad:** "retrato" JSON de filtros y salidas con los snippets en WPCodeBox y con
el plugin, y comparar (debe ser idéntico).

**Probar en la copia:** apagar el snippet en la tabla `wpcb_snippets` (`enabled=0`) **y** comentar su
`include_once` en `wpcodebox_functionality_plugin/plugin.php` del volumen (copia en `plugin.php.orig`).
Contexto admin desde WP-CLI: `--require` de un archivo con `define('WP_ADMIN', true)`.

**Bug de producción para U10:** `disable-turnstile-woocommerce-ajax.php` empieza con un espacio
antes de `<?php` (sale antes de las cabeceras; en la copia rompía wp-admin sin `output_buffering`).

## 7. Al cerrar la migración (antes del deploy)

- Plugin `inversionesjl22-site` con todo lo de §1 y §2; WPCodeBox 2 **y**
  `wpcodebox_functionality_plugin` desactivados en la copia, recorrido completo (paso 6).
- En producción se desactivan **en el mismo deploy** que trae el plugin, nunca antes ni después.
