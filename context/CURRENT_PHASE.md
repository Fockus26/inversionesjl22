# Current Phase — InversionesJL22

> **A board, not a diary.** ≤ 60 lines / 4 KB. Local file: backed up with
> `bun run context:backup` (branch `context-state`). Lo anterior al 2026-11-13 (adopción, fase 2,
> recorridos de staging): `PHASE_LOG/tablero-hasta-2026-11-13.md`.

## Now

**Phase 3: plugin configurable desde wp-admin** (desde 2026-11-12). Cada unidad en su propia
sesión, con el modelo y el esfuerzo que la tarea pida (César, 2026-11-13).

| Unit | Branch | Status |
|---|---|---|
| Adopción (1–26) y fase 2 | — | en `main` (#5–#32) |
| F3·1 motor, 2a pantalla Estados y flujo, 2b color e icono | — | en `main` (#33–#35) |
| F3·2c acciones según el flujo, F3·3 Roles y permisos (+4 crear roles) | — | en `main` (#36, #37) |
| F3·3b QR asignable por rol | — | en `main` (#38) |
| F3·5 Documentos (PDF): papel 80/58 mm (+ tipografía/grosor, que quedó fuera de #39: va en F3·6) | — | en `main` (#39) |
| F3·6 PDF propios (Dompdf + informe; factura + entradas + migración 007) | `feat/pdf-propio` → `feat/factura-propia` | PR #40 y #41 en cadena; 007 espera la respuesta de César (adjunto en correos, Mi cuenta) |
| F3·7 identidad y 1.0.0 | — | pendiente |

**Al terminar toda la fase 3:** entregar a César la lista de pruebas a hacer en staging. Nada pasa
a producción hasta que él se lo muestre al cliente (César, 2026-11-13).

**Entornos:** producción en `main` 3125c1b (#23, desplegado 2026-11-11; respaldo
`~/backups-claude/prod-pre-plugin-20261111-2150.sql.gz` en el servidor). Fase 2 (#24–#32) y fase 3:
solo en staging. Al desplegar a producción corren las migraciones 004 (desactiva URE) y 005
(Loco/YITH POS); luego purgar Cloudflare.

**Decisiones vigentes:** editor del flujo A y matriz por rol A (canvas de diseño); botón primario
blanco sobre terracota (D001).

## Blocked (actions for César or external)

- Revisar staging con las cuentas de bodega y SEO.
- F3·6: ¿se mantienen el adjunto de la factura en correos (en uso: «Nuevo pedido» de cada pedido) y el botón de Mi cuenta? Sin respuesta no se despliega la 007.
- Informe de envíos (`shipping-report/`): sigue con roles fijos (pendiente, sin unidad aún).
- Producción: mostrar al cliente antes. Reversa del plugin:
  `wp plugin activate wpcodebox2 wpcodebox_functionality_plugin && wp plugin deactivate inversionesjl22-site`.
- Plugins con actualización pendiente (Woodmart 7.2.5 muy atrás).

## Notes of the copy (local only)

- Muy lenta el 2026-11-13 (30–60 s por petición): pruebas largas en segundo plano.
- Desactivados en la base local: wp-mail-smtp, litespeed-cache, simple-cloudflare-turnstile,
  facebook-for-woocommerce, google-listings-and-ads, google-site-kit. `DISABLE_WP_CRON` en el compose.
- El código de wp-content vive en el volumen `inversionesjl22_wp-content`: tras cada `site:pull`,
  `bun run local:sync`. El plugin del sitio, el tema hijo y PDF Invoices se montan desde el repo.
- Pruebas: `node` + Playwright canal `msedge` en `.qa/`; capturas con `.qa/shots-edge.js <carpeta>`
  (`bun run shots` falla: falta Chromium). Tras una prueba que guarde configuración, borrar las
  opciones `inversionesjl22_site_config` y `inversionesjl22_site_config_previous`.

## Next

1. Revisar los dos PR de F3·6; luego borrar PDF Invoices vendorizado (unidad siguiente) o la 7.
