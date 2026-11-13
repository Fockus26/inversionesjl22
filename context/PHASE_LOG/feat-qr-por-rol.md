# feat/qr-por-rol — Fase 3, unidad 3b: QR asignable por rol

**Base:** `main` 665f4b0 (#36 y #37 ya fusionados).

**Pedido (César, 2026-11-13):** «El escaneo de QR que no solo sea fijo para storage_manager sino que
se pueda asignar o quitar a otros roles».

**Qué:** `config['roles'][rol]['qr_scan']` (bool; fábrica: bodega sí, SEO no; rol nuevo no). Un rol
guardado sin la clave: solo `storage_manager` (`inversionesjl22_site_role_scans_qr()` en config.php).
`inversionesjl22_site_user_can_scan_qr()` sustituye los cuatro `current_user_can('storage_manager')`
de qr-scan.php (script, pie, escaneo, AJAX). Tarjeta «Escaneo del QR de la factura» en Roles y
permisos (tercera junto a Pasos propios y Pantallas del menú), casilla `qr_scan`.

**Decisiones:**
- Administrador: hoy no escanea (la comprobación era el rol storage_manager, que no tiene). Se
  conserva: el administrador no cuenta como rol con QR; un admin que además tenga un rol con QR sí.
- El escaneo solo completa si el rol ve Impreso y Completado y su flujo tiene Impreso → Completado
  (`inversionesjl22_site_qr_can_complete()`); si no, no cambia nada y el aviso dice «Tu rol no puede
  completar esta orden». Hoy el escaneo no miraba ninguna regla. `exclude['qr_actions']` NO se usa:
  es el botón de factura PDF de la lista (roles-actions.php), no el escaneo.
- Los avisos de los otros estados (en espera, cancelada…) siguen igual aunque el rol no vea ese
  estado: cambiarlos rompía «de fábrica idéntico» para bodega.

**Prueba:** `.qa/qr-por-rol.js` (admin, bodega, SEO); identidad: `.qa/config-identidad/`
(`before-u3b.json`, `after-u3b.json` de fábrica, `after-u3b-saved.json` tras `guardar-sin-cambios.php`;
`dump.php` ahora incluye `qr` por rol).

**Pendiente fuera de alcance:** el informe de envíos (`shipping-report/`) sigue con roles fijos.

**Verificado (2026-11-13):** PR #38. `.qa/qr-por-rol.js` 30/31; la que falló («bodega ve el aviso en
el equipo que escaneó») pasó aparte con `.qa/qr-por-rol-debug.js` (copia lenta). Identidad por rol
idéntica (de fábrica y tras guardar sin cambios); lint verde. Pedidos 70350/70345 de vuelta en Impreso
(70350 sin `_qr_completed_at`; quedan notas de prueba en la copia local).
**No verificado:** staging; cámara real; rol creado desde la pantalla con QR.
