# feat/pantalla-estados-flujo — Fase 3, unidad 2a: pantalla Estados y flujo (editor A)

**Qué:** WooCommerce › Estados y flujo (solo administradores). Ordenar estados, renombrarlos
(también los de WooCommerce), texto del botón, "puede pasar a" (chips), pedido editable, exige
estado de pago, crear y eliminar estados propios, restaurar valores iniciales. Guarda en la
opción `inversionesjl22_site_config`; la anterior queda en `inversionesjl22_site_config_previous`.
Rama abierta sobre feat/config-estados-roles (#33).

**Configuración:** el orden del arreglo `statuses` es ahora el de listas y pestañas (se quitó
`after`); `requires_payment` por estado; `label` opcional en los de WooCommerce (renombrar).

**Verificado (copia local, 2026-11-12):** `.qa/estados-flujo.js`, 35/35: axe WCAG 2.1 AA (plegado
y abierto), foco visible, orden con teclado y anuncio, guardar, crear, conectar, renombrar,
duplicados rechazados (nombre de otro estado o de WooCommerce), eliminar, restaurar, 320 px,
storage/seo sin acceso (redirigidos; POST 403). Foto por rol idéntica con valores de fábrica.

**Hallazgos corregidos en la unidad:** aceptaba un estado nuevo con nombre repetido (el slug de
"Reservado" es `reservado`, no `reserved`); "Procesando" salía como opcional (ahora: obligatorio
si algún estado final solo se alcanza pasando por él).

**No verificado:** arrastrar con ratón (solo flechas); lector de pantalla real; staging.

**Queda para 2b / 2c:** color e icono por estado; acciones del administrador desde el flujo (un
estado nuevo solo se usa hoy desde el selector del pedido); QR e informe siguen fijos.
`bun run shots` falla en esta máquina (falta el Chromium de Playwright): capturas con
`.qa/shots-edge.js` (Edge).
