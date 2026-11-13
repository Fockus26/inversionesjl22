# chore/identidad-1-0-0 — F3·7 identidad y 1.0.0 (2026-11-13)

Rama sobre `origin/feat/pdf-propio` (PR #42 abierto: fusionarlo antes).

- Cabecera propia en español: «Inversiones JL22 — Tienda», `Version: 1.0.0`, `Requires Plugins: woocommerce`,
  `Requires at least: 7.1`, `WC requires at least / tested up to: 11.2` (versiones reales de la copia: WC 11.2.2).
  Sin `Author` / `Author URI`: el repo no los da (pregunta en el PR). Fuera el comentario de la plantilla del kit.
- `INVERSIONESJL22_SITE_VERSION` = `1.0.0`. Nada renombrado (carpeta, prefijos, constantes, opciones, text domain).
- Versión y migraciones: las migraciones dependen de `inversionesjl22_site_db_version` (contador por número), no de
  la versión del plugin → subirla no vuelve a ejecutar nada. Los assets usan `inversionesjl22_site_asset_version()`;
  el único uso de la constante como versión es el handle de CSS en línea de la lista de pedidos (sin archivo): cambia
  con cada versión, correcto.
- Barrido: aviso de WPCodeBox con el nombre nuevo. Señalado, no borrado: `migrations/001-example.php` (ejemplo inofensivo,
  ya registrado en la base; borrarlo es otra decisión).
- `README.md` del plugin con pantallas, requisitos, reinicio y registro de la 1.0.0 (PR #33–#42).
- QA: `.qa/identidad-1-0-0.js`.
