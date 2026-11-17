# fix/seguridad-qr-roles — tres puntos de la revisión de seguridad (2026-11-17)

Origen: `.qa/comparativa/analisis/seguridad-verificada.md` (P1, P6, P3). Una sesión, tres commits.

## Qué se hizo

1. **QR firmado** (`includes/qr-scan.php`, `templates/pdf/invoice.php`). El QR lleva `&sig=`:
   HMAC-SHA256 de `qr_scan|ID|clave de pedido` con `wp_salt('auth')`, 20 hex, `hash_equals`.
   Firmado → un escaneo completa, como antes. Sin firma o inválida → página de confirmación
   propia (no del tema) con botón que hace POST con nonce `inversionesjl22_site_qr_scan_<ID>`;
   POST sin nonce válido → 403. El login conserva la firma. Respuesta con `nocache_headers()`.
   - La firma depende de las sales de cada entorno: un QR impreso en producción no es válido en
     staging (allí pide confirmación). Si se rotan las sales, los QR impresos piden confirmación.
2. **QR en el sitio** (`generateQrCode()`): chillerlan/php-qrcode 5.0.5 + php-settings-container
   2.1.6 en `vendor/chillerlan/` con prefijo `InversionesJL22Site\Vendor\`. PNG escrito a mano
   (zlib, sin GD), ECC M, versión 5 para la URL firmada (~83 caracteres).
3. **Roles que fallan cerrado**: `getUserPermissions()` devuelve para un rol no configurado un
   ruleset con todos los estados en `false`, sin flujo y sin «Combinar»;
   `filterSearchOrderStatuses` con 0 estados → sin pedidos; el menú del plugin solo registra el
   informe de envíos para admin o un rol configurado (`inversionesjl22_site_user_has_configured_role()`).
   Sin migración. Hoy no hay usuarios afectados (shop_manager, author… con 0 usuarios en la copia).

## Verificado en la copia

- Pedidos de prueba 70795–70802 (`_qa_seguridad`, sin datos de clientes). Scripts en `.qa/seguridad-qr-roles/`.
- QR (`qr.js`): firmado completa en un paso; sin firma → confirmación y el botón completa;
  firma falsa + POST sin nonce / con nonce falso → 403 y el pedido sigue Impreso; SEO con enlace
  firmado no cambia nada; anónimo → login con la firma. axe sin violaciones, 320 px sin scroll.
- PDF 80 y 58 mm (`factura.php`): QR decodificado con el lector de chillerlan sobre las páginas
  rasterizadas con pdf.js (escala 4 y 1,5) → la URL firmada exacta.
- Roles (`roles.js`, `reglas.php`): admin, bodega y SEO iguales antes y después; cuenta
  `qa_sin_reglas` (shop_manager) → 0 pedidos, sin acciones, informe 403, no abre pedidos.

## No verificado

- Escaneo con un teléfono de un ticket impreso en térmica (solo decodificación de la imagen).
- jsQR desde CDN quedó denegado; la decodificación usa el lector de la misma librería (código
  distinto del codificador, port de ZXing).

## Para después

- La URL del QR sigue fija a `https://inversionesjl22.com` (como antes): en staging el QR apunta a producción.
- El informe de envíos aún autoriza por nombre de rol (P5 de la revisión).
