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
| 12 imagen Open Graph | `feat/site-og-image` | PR #16 en revisión |
| 13 a11y estado de pago | `fix/site-payment-status-a11y` | PR #17 en revisión |
| 14 hallazgos del código migrado | `fix/site-snippet-findings` | PR #18 en revisión (#16 y #18 tocan la misma zona del cargador: el segundo en mergearse puede pedir resolver un conflicto) |

## Blocked (actions for César or external)

- Revisar y mergear #16, #17, #18 (base `main`).
- **Informe de Envíos roto en producción** (probable): `\Dompdf\Dompdf` no existe en PDF Invoices
  5.16.6 → error fatal al generar el PDF. Arreglado en #18, llega con el deploy que apague WPCodeBox.
- Secrets de deploy en GitHub (GoDaddy: llave SSH + contraseña).
- Decidir: `yith-activator` (plugin pirata, inactivo); limpiar cron huérfano (§5 del plan) con
  una migración; SKU sin categoría = `SIN-CATEGORIA-<id>` (categoría por defecto) ¿ok?

## Notes of the copy (local only)

- Desactivados en la base local: wp-mail-smtp, litespeed-cache, simple-cloudflare-turnstile,
  facebook-for-woocommerce, google-listings-and-ads, google-site-kit. `DISABLE_WP_CRON` en el compose.
- Apagados en `.site-pull`: `object-cache.php`, mu-plugins `gd-system-plugin` y `object-cache-pro` (`.off`).
  `disable-turnstile-woocommerce-ajax.php` sigue en la copia (imprime un espacio antes de las cabeceras).
- El código de wp-content vive en el volumen `inversionesjl22_wp-content`: tras cada `site:pull`,
  `bun run local:sync`. El plugin del sitio, el tema hijo y PDF Invoices se montan desde el repo.
- Dompdf genera cachés de fuentes en `woocommerce-pdf-invoices-packing-slips/vendor/strauss/dompdf/dompdf/lib/fonts/` (sin ignorar en git).

## Next

1. Tras mergear #16–#18: en la copia, apagar WPCodeBox 2 y `wpcodebox_functionality_plugin` y hacer
   el recorrido completo (paso 6 de `wordpress-adopt.md`) con storage_manager y seo_manager.
2. Deploy (paso 5): workflow deploy-ssh → staging de GoDaddy → producción. En producción el plugin
   se activa y WPCodeBox se desactiva **en el mismo deploy**.
3. Después: plugins con actualización pendiente (Woodmart 7.2.5 muy atrás) — César.
