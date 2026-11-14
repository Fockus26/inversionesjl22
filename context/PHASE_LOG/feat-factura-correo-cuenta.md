# feat/factura-correo-cuenta — F3·6b: la factura en los correos y en Mi cuenta

**Base:** salió de `feat/pdf-propio` (fb8e823); tras fusionarse #43 en `main` (que ya traía la factura
propia y la 007, desplegado a staging el 2026-11-13 21:25Z) se fusionó `origin/main` y el PR va a `main`.
#42 queda redundante. La rama incluye `ed48454` de otra sesión (avisos de migraciones en español + fuera
`001-example.php`, aprobado por César el 2026-11-13).

**Pedido (César, 2026-11-13):** «Sí, mantener ambos» (el adjunto en correos y el botón de Mi cuenta).

## El «antes» (PDF Invoices 5.16.6, ajustes de la base copiada de producción)
- `wpo_wcpdf_documents_settings_invoice`: `enabled`, `attach_to_email_ids` = new_order,
  customer_completed_order, customer_refunded_order, customer_invoice, customer_note;
  `my_account_buttons = available`.
- Nombre del adjunto (observado en Mailpit): `factura-<número de factura de PDF Invoices>.pdf` (p. ej.
  `factura-38280.pdf`; `Invoice::get_filename()` traduce «invoice» y usa `display_number = invoice_number`).
  Sin esa numeración: `factura-<número de pedido>.pdf`. (La descarga de wp-admin propia, de #41, sigue
  como `invoice-N.pdf`: reportado, no tocado.)
- Reembolso parcial: correo `customer_partially_refunded_order`, no configurado → sin factura, hoy y ahora.
- Observado con PDF Invoices activo: botón «Factura», aria «Factura número de pedido N», pestaña nueva;
  descarga con `Cache-Control: public, must-revalidate, max-age=0`; visitante con el enlace → 200 con
  «No tienes suficientes permisos…» (no PDF); enlace para invitado = vacío.
- Mi cuenta «available»: botón solo si la factura existe (`_wcpdf_invoice_date`), en cualquier estado;
  se crea en la práctica con el adjunto de «Nuevo pedido». Filtro `woocommerce_my_account_my_orders_actions`
  (999) → sale en Mi cuenta › Pedidos, la página del pedido y la de pedido recibido. Texto «Factura».
  `download_display = display`: un JS abre el enlace en pestaña nueva, PDF inline.
- Acceso: `document_link_access_type` sin ajuste = `logged_in` → enlace con nonce del usuario; el
  endpoint exige sesión, nonce y `current_user_can('view_order')` (dueño). Invitado: el enlace sale vacío
  (botón roto en la página de pedido recibido), no descarga. Denegado: `wp_die` (página en blanco).
- Producción envía con WP Mail SMTP (mailer smtp, sin cola, `save_attachments` apagado): síncrono, así que
  borrar el temporal tras el envío es seguro.

## Qué se hizo (`includes/invoice-customer.php`)
- `woocommerce_email_attachments` (99) en los cinco correos (filtro `inversionesjl22_site_invoice_email_ids`):
  PDF en `get_temp_dir()/jl22-invoice-<aleatorio>/factura-N.pdf`, borrado en `woocommerce_email_sent`
  y en `shutdown`. Fallo → el correo sale sin adjunto, `wc_get_logger()->error` (fuente inversionesjl22-site).
- «Existe factura» = `_wcpdf_invoice_date` o `_inversionesjl22_invoice_date` (se pone al adjuntarla o al
  sacarla desde wp-admin).
- Mi cuenta: acción `invoice` «Factura», aria-label con número y «se abre en una pestaña nueva», JS de una
  línea para `target=_blank`. Solo al dueño con sesión (invitados, como hoy, sin descarga; sin el botón roto).
- Endpoint `admin-ajax.php?action=inversionesjl22_my_invoice&order_id&_wpnonce` (nonce por pedido),
  también `nopriv` para responder 403 al visitante; `nocache_headers()` + `X-Robots-Tag`.
- Interruptor: con PDF Invoices activo nada se añade.
- 007: su comprobación exige también estas dos piezas (en staging ya corrió; en producción aún no). No hace
  falta 008: no hay datos que cambiar.

## Pruebas
- 3.ª pasada 32/33: el único fallo, artefacto de la comparación de axe (selector distinto para «Ver»),
  corregido en la prueba. Foco: el tema quita outline/sombra a los botones de Mi cuenta → anillo propio
  `#4a4959` en el botón de factura.
- **Hallazgo previo (no de esta unidad):** los botones de Mi cuenta del tema (Ver, Factura de PDF
  Invoices y el propio) son blanco sobre `#ffb16f`: 1,78:1. Decisión visual para César (D001 dice blanco
  sobre terracota `#a4521a`).
- La prueba descuenta existencias del producto que usa; ahora las devuelve. Las pasadas 1–2 dejaron el
  #70211 en 0: devuelto a 4 (valor del volcado del 2026-11-09).
`.qa/factura-correo-cuenta/prueba.js` (resultado en `resultado.json`, capturas en `shots/`).

## Siguiente
- Borrar la carpeta vendorizada de PDF Invoices y lo asociado (unidad aparte).
- Comprobar en staging: un pedido real, el correo fuera de Mailpit, Cloudflare no cachea `admin-ajax.php`.
