# feat/acciones-admin-por-flujo — Fase 3, unidad 2c: acciones según el flujo

**Qué:** las acciones rápidas, las de lote y las de la vista previa que `order-statuses.php` añadía
estado por estado salen ahora del flujo de la configuración: una por cada estado al que el pedido
puede pasar. Un estado creado en Estados y flujo ya se puede usar desde la lista de pedidos.

**Cambia para el administrador:** gana "Cancelar" (acción rápida, vista previa) en En espera,
Reservado y Procesando; los botones dicen el texto del botón (Reservar, Procesar, Completar) y no
el nombre del estado; "Cambiar estado a cancelado" en lote pasa a ser nuestro texto.
**No cambia para bodega y SEO** (foto por rol idéntica). Para eso `roles-actions.php` limpia ahora
todas las acciones de estado antes de construir las del rol: sin ese cambio heredaban "Cancelar".

**Nota de la prueba:** la columna de acciones no es visible al ancho de la prueba; se va al href.
La copia local estuvo muy lenta el 2026-11-13 (≈60 s por cambio de estado).

**Hallazgo corregido (importante):** `isStatusAllowedForAction()` daba por permitido un estado sin
regla en el rol: bodega y SEO podían mover pedidos a un estado nuevo conectado en el flujo (en
`main` desde #34). Ahora `empty($ruleset[$status])` → no. Comprobación: `.qa/config-identidad/sin-permiso.php`.
También: pestañas de Reservado/Impreso con `<li>` anidado → solo el enlace.

**Verificado (2026-11-13):** foto por rol idéntica para bodega y SEO; `.qa/acciones-por-flujo.js` 15/15.
**No verificado:** ejecutar de verdad una acción en lote sobre varios pedidos; staging.
