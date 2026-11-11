# Current Phase — InversionesJL22

> **A board, not a diary.** ≤ 60 lines / 4 KB. Local file: backed up with
> `bun run context:backup` (branch `context-state`).

## Now

**Phase:** Adopción del kit (sitio existente) · paso 4A (migrar código al plugin) casi cerrado ·
guía: kit `reference/tech/wordpress-adopt.md` · plan: `plans/adoption.md`

| Unit | Branch | Status |
|---|---|---|
| 1–9, 11 (24 snippets + SKU) | — | en `main` (#5 y #15, integración de la cadena #6–#14) |
| 10 mu-plugin Turnstile | — | descartada: no hacía nada; César lo apagó en producción |
| 12–14, paso 5 (OG, a11y pago, hallazgos, deploy SSH) | — | en `main` (#16–#19) |
| 15 limpieza: cron huérfano + yith-activator | `chore/site-cleanup` | PR #20 en revisión (migraciones 002 y 003) |
| 16 roles: vista previa y estados por URL | `fix/roles-preview-url-status` | PR en revisión (ya subido a staging a mano) |

## Blocked (actions for César or external)

- Revisar y mergear #21 (`.env.example`: variables de staging).
- Entornos `staging` y `production` con sus 6 secrets DEPLOY_* (staging: `DEPLOY_PATH=html`,
  supuesto; huella = la de producción, clúster us32). `production` solo acepta `main`, sin revisor
  obligatorio (el plan gratuito no lo permite en repo privado). 1.er deploy a staging OK (run 42732238808): plugin subido, inactivo, migraciones saltadas.
- **Informe de Envíos roto en producción** (probable): arreglado en `main`, llega con el deploy
  que apague WPCodeBox.

## Notes of the copy (local only)

- Desactivados en la base local: wp-mail-smtp, litespeed-cache, simple-cloudflare-turnstile,
  facebook-for-woocommerce, google-listings-and-ads, google-site-kit. `DISABLE_WP_CRON` en el compose.
- Apagados en `.site-pull`: `object-cache.php`, mu-plugins `gd-system-plugin` y `object-cache-pro` (`.off`).
  `disable-turnstile-woocommerce-ajax.php` sigue en la copia (imprime un espacio antes de las cabeceras).
- El código de wp-content vive en el volumen `inversionesjl22_wp-content`: tras cada `site:pull`,
  `bun run local:sync`. El plugin del sitio, el tema hijo y PDF Invoices se montan desde el repo.
- Dompdf genera cachés de fuentes en `woocommerce-pdf-invoices-packing-slips/vendor/strauss/dompdf/dompdf/lib/fonts/` (sin ignorar en git).

## Next

1. **Recorrido del paso 6 hecho en la copia (2026-11-10) con WPCodeBox apagado**: tienda, carrito
   (Limpiar con nonce), checkout de visitante → pedido, correos en Mailpit, factura 80 mm con QR,
   Informe de Envíos, y los 3 roles: idéntico con WPCodeBox encendido. WPCodeBox queda apagado en la copia.
2. **Staging hecho (2026-11-10)**: plugin activo, WPCodeBox 2 + functionality apagados, migraciones
   001–003 corridas (002 no quitó nada: staging no tenía ese cron; yith-activator borrado). Páginas
   públicas 200 sin errores. Falta: César revisa wp-admin de staging con sus roles.
   2026-11-11: staging tenía PDF Invoices 3.2.3 (clon viejo, datos hasta 23-01-2026): se subió la
   5.16.6 del repo (respaldo de la 3.2.3 en `~/backups-claude/` del servidor de staging).
   Staging: WP Mail SMTP inactivo + mu-plugin `staging-block-mail.php` (ningún correo sale).
   Roles probados en staging con las 3 cuentas (2026-11-11). Hallazgos en código cerrado (también
   en producción): vista previa sin filtro de rol (usa global $post, vacío en AJAX); estados
   ocultos visibles por URL (`do-not-match` lo ignora WP_Query: storage ve en espera/papelera,
   seo puede borrar de la papelera); URE Pro manda a storage al Escritorio tras cada acción rápida.
   Luego **producción** (César): Run workflow → production, y el mismo cambio (activar plugin,
   apagar WPCodeBox, migrate) — idealmente en hora de poco tráfico.
3. Después: plugins con actualización pendiente (Woodmart 7.2.5 muy atrás) — César.
