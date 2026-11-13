# feat/config-estados-roles — Fase 3, unidad 1: motor de configuración

**Qué:** estados propios, flujo, reglas por rol y pantallas por rol salen de un solo arreglo
(`includes/config.php`): opción `inversionesjl22_site_config` o, si nadie la guardó, los valores
de fábrica (lo que estaba escrito en el código). Sin interfaz todavía.

**Verificado (copia local, 2026-11-12):**
- Foto antes/después por rol (administrator, seo_manager, storage_manager) y por estado con
  pedidos (6): reglas, flujo, menús, estados y su orden, acciones rápidas, vista previa, lote,
  selector del pedido, pestañas, `role_can_move` (7×7×3 contextos). Idéntico; solo cambia el
  orden interno de dos listas. Scripts: `.qa/config-identidad/` (`docker exec -i … php < dump.php`,
  `python cmp.py after.json`).
- Opción guardada con un estado nuevo (Empacado tras Impreso): se registra, queda en su sitio,
  tiene endpoint `orderToPacked`, storage lo ve como acción rápida y el servidor lo valida.
- Opción dañada (texto) → valores de fábrica. Opción borrada → valores de fábrica.
- Navegador: `.qa/storage-flujo.js` (Procesando → Impreso con storage) y `acciones-rapidas.js`.

**No verificado:** seo_manager en navegador (solo por la foto); QR (no se tocó).

**Fuera de esta unidad (a propósito):**
- Acciones del administrador en `order-statuses.php` (rápidas, lote, vista previa) siguen estado
  por estado: hacerlas genéricas cambia lo que ve (p. ej. botón Procesando duplicado en la vista
  previa de En espera). Va con la unidad 2.
- Color e icono por estado: se añaden con la pantalla (unidad 2).
- `qr-scan.php` sigue con storage_manager e Impreso → Completado fijos; informe de envíos con
  sus estados fijos.
- Sin migración: no se siembra la opción (menos estado que mantener; borrar = restaurar).
