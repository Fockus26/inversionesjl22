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
| F3·2c acciones según el flujo | `feat/acciones-admin-por-flujo` | PR #36 |
| F3·3 pantalla Roles y permisos (incluye la 4, crear roles) | `feat/pantalla-roles-permisos` (sobre #36) | PR #37, fusionar #36 antes |
| F3·3b QR asignable por rol (hoy fijo en `storage_manager`) | sobre la de #37 | en su sesión |
| F3·5 Documentos (PDF) | — | pendiente |
| F3·6 reemplazar PDF Invoices | — | pendiente; César decide: ¿numeración, adjunto en correo, Mi cuenta? |
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

- Fusionar #36 y luego #37; revisar staging con las cuentas de bodega y SEO.
- Decisión de la unidad 6 (qué se usa hoy de PDF Invoices).
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

1. QR asignable por rol (en su sesión).
2. Unidad 5, Documentos.
