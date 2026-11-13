# feat/pdf-propio + feat/factura-propia — Fase 3, unidad 6: los PDF sin PDF Invoices

**Base:** `main` fa3eca9 (#39). PR #40 → #41 en cadena: 1) `feat/pdf-propio` (Dompdf propio + informe de
envíos, + el commit a56f1a4 de tipografía/grosor que quedó fuera de #39); 2) `feat/factura-propia`
(factura + entradas + migración 007), base la 1.

**Pedido (César, 2026-11-13):** «el pdf genera los pdf de las facturas de los pedidos, genera el pdf
del informe de envíos».

## Inventario (2026-11-13, copia local con datos de producción)

**Código que usa PDF Invoices (5.16.6 vendorizado):**
- `includes/qr-scan.php`: `generateQrCode()` (la llama la plantilla); filtros `wpo_wcpdf_item_price`
  (IVA) y `wpo_wcpdf_billing_address` (cédula) bajo `class_exists('WPO_WCPDF')`. **Ninguno corre:** 5.16.6
  no tiene `wpo_wcpdf_item_price` y la plantilla no usa `billing_address()`. Quedan hasta borrar el
  plugin vendorizado.
- `includes/roles-actions.php`: filtro `wpo_wcpdf_listing_actions` (quita el botón «invoice» de la
  lista si el estado está en `exclude['qr_actions']` o el rol no lo ve).
- `includes/shipping-report/init.php` → `do_action('wpo_wcpdf_custom_report_pdf')` (acción propia con
  nombre de PDF Invoices) → `pdf.php` usaba `WPO\IPS\Vendor\Dompdf\Dompdf`; `pdf-ui.php` leía
  `custom/shipping-report.css`. Estilos del formulario: `custom/shipping-report-admin.css`, encolado por
  `custom/inversionesjl-pdf.php`.
- `includes/config.php` / `settings-documents.php` / migración 006: solo comentarios y leer
  `wpo_wcpdf_settings_general` (006).
- Tema hijo `woodmart-child/inc/…`: referencias a `wpo_wcpdf_*`, pero **el tema hijo no está activo**
  (`stylesheet = woodmart`; su functions.php pide un archivo que no existe). Código muerto, no tocado.
- `custom/inversionesjl-pdf.php`: papeles 80/58, alto automático, tipografía/grosor, CSS de 58 mm.

**Quién lo dispara y qué rol lo ve:**
| Entrada | Hoy (PDF Invoices) | Roles |
|---|---|---|
| Botón en la lista (`woocommerce_admin_order_actions_end`, `admin-ajax.php?action=generate_wpo_wcpdf&document_type=invoice&order_ids=N&_wpnonce`) | sí | admin todo; roles: oculto si estado en `qr_actions` o no visible |
| Caja «Crear PDF» del pedido (Factura) | sí | quien abre el pedido (sin mirar `qr_actions`) |
| Acción en lote «PDF Factura» (JS abre pestaña) | sí; 70 facturas creadas por lote (2024-08 → 2026-10-25) | admin; roles solo en vistas de estado (`filterBulkActionsOderStatuses`) |
| Adjunto en correos | **en uso**: `attach_to_email_ids` = new_order, customer_completed_order, customer_refunded_order, customer_invoice, customer_note; 464 facturas creadas por `email_attachment`, la última 2026-11-09 (#70403) | — |
| Mi cuenta | `my_account_buttons = available` (configurado; sin dato de uso) | clientes |
| Albarán | sin `enabled` en sus ajustes → **apagado** | — |
| Numeración | 38 152 pedidos con `_wcpdf_invoice_number` (máx. 38276), se crea como efecto del adjunto. **La factura impresa no la muestra** (usa el número de pedido) | solo la caja de datos de PDF Invoices |
| Endpoint | no mira reglas de rol: cualquier `edit_shop_orders` con nonce saca cualquier factura | |

**Pregunta a César (enviada 2026-11-13):** ¿se mantienen el adjunto en correos y Mi cuenta? Sin
respuesta al cerrar → no se reconstruyen en este PR; la migración 007 no debe desplegarse sin su sí.

## Referencias «antes» (`.qa/pdf-propio/antes*/`)
Pedidos 69917 (17 productos, MRW, USD), 70328 (contra entrega, por cobrar 117 500 pesos), 70228
(Interrapidísimo, nota del cliente, variaciones) y el lote de los 3; informe CO 2026-11-06 (11 pedidos).
`genera.php` + `guarda.js` (texto pdftotext, páginas, MediaBox), `html.php` (HTML de Dompdf), `compara.js`
(píxeles con pdf.js + QR con jsQR).

## Resultado
- Informe 80 y 58 mm: **idéntico byte a byte** salvo fecha de creación e `/ID`.
- Facturas 80 y 58 mm (3 + lote): mismo texto, mismas páginas (3 / 9), mismos anchos y altos
  (80: 1299 / 580 / 525 pt; 58: 1503 / 619 / 583). Única diferencia de píxeles: el «$». En la copia
  local PDF Invoices no encuentra su fuente `currencies` (su `uploads/wpo_wcpdf_*/fonts/installed-fonts.json`
  trae rutas del servidor de producción) y dibuja un recuadro tras el «$»; el propio dibuja el «$»
  limpio, como en producción. QR leído: `https://inversionesjl22.com?action=qr_scan&order_id=N`.
- Tipografías de Documentos con el motor propio: Open Sans, Segoe, DejaVu Serif, Roboto Slab, Times OK.

## Decisiones
- Dompdf: copia del de `vendor/strauss/` (3.1.6, PHP 7.1+) con prefijo `InversionesJL22Site\Vendor\`
  (no choca con `WPO\IPS\Vendor\`); sin READMEs/tests, sin cursivas DejaVu (no hay cursivas: la dist.json
  las apunta a las rectas), sin cachés `.ufm.json` (van a `uploads/inversionesjl22-site/fonts`). 7,2 MB
  + 2,9 MB de fuentes (Open Sans, Roboto Slab, Segoe normal/negrita, currencies). Un solo parche a la
  copia: `FontLib\TrueType\File::getFontType()` contaba las partes del espacio de nombres de strauss.
  Alternativa descartada: Dompdf limpio de Packagist (no hay composer en el host; otro prefijo a mano
  igual, y sería otra versión que la que hizo los PDF de hoy).
- Reglas: la columna «Factura PDF» (`qr_actions`) y ver el estado mandan en las tres entradas propias y
  en el endpoint. Cambio frente a hoy: la caja del pedido ya no da la factura de un estado excluido en
  esa columna (bodega: completados; SEO: impresos/completados).
- Interruptor: `inversionesjl22_site_own_invoice()` = PDF Invoices inactivo (filtro para forzar).
- HTML sin `htmlspecialchars_decode` global de PDF Invoices (convertía `&lt;` de datos del cliente en
  etiquetas); `isRemoteEnabled` apagado (el QR es data:).
- Sin «exists»/«printed» en el botón (venían de la numeración de PDF Invoices).

## Siguiente
- Respuesta de César sobre adjunto en correos y Mi cuenta (antes de desplegar 007).
- Borrar la carpeta vendorizada de PDF Invoices, `custom/`, el filtro `wpo_wcpdf_listing_actions` y los
  filtros de IVA/cédula de qr-scan.php, y la regla de `.gitignore`.
- El QR apunta a `https://inversionesjl22.com` fijo (también en staging) y se pide a api.qrserver.com.

## Cierre (2026-11-13)
- `roles.js` 53/54 (el fallo era de la prueba: columna «Acciones» oculta para usuarios nuevos; `foco.js` OK).
- Copia local: PDF Invoices **desactivado** por la 007 (db_version 7).
- Mu-plugin local `disable-turnstile-woocommerce-ajax.php` imprime un espacio: la factura y el informe propios limpian el búfer antes del PDF.
