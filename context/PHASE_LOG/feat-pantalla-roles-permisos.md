# feat/pantalla-roles-permisos — Fase 3, unidad 3: pantalla Roles y permisos

**Base:** `feat/acciones-admin-por-flujo` (PR #36 seguía abierto): fusionar #36 primero.

**Qué:** WooCommerce › Roles y permisos (`includes/settings-roles.php`, `assets/js/settings-roles.js`,
más CSS en `admin-settings.css`), diseño A «matriz por rol». Una vista por rol (`&role=<slug>`):
matriz estado × permiso, «Pasos propios de este rol», «Pantallas que ve en el menú». «+ Nuevo rol»
(`&new=1`) crea un rol de WordPress copiando capacidades; un rol creado aquí (`custom => true` en su
regla) se puede eliminar si no tiene usuarios. «Restaurar» devuelve solo bodega y SEO a fábrica.
Pestañas Estados y flujo / Roles y permisos en ambas pantallas (`inversionesjl22_site_settings_tabs()`).

**Decisiones (en el PR):**
- Fila apagada (el rol no ve el estado): sus casillas se muestran con lo que tenían y deshabilitadas,
  y al guardar se conservan (un campo oculto `perm[slug][row]` dice si la fila se envió). El mockup
  las pintaba vacías; vaciarlas rompía «guardar sin cambios = idéntico».
- Claves de menú válidas: `admin-post.php` no construye `$menu`; la pantalla deja la lista ofrecida
  en un transient por usuario (1 día) y el guardado solo acepta esas o las que el rol ya tenía.
- Rol nuevo: copia `screens` y `admin_bar` del rol base (la pantalla no los edita y sin
  `post.php?post_type=shop_order` no podría abrir un pedido).
- `inversionesjl22_site_flow($rol)`: los pasos del rol se SUMAN a los orígenes globales del destino
  (antes `array_merge` los reemplazaba; con la configuración de fábrica el resultado es el mismo).
- Menú: marcadas arriba, el resto tras «Añadir otras pantallas» (`<details>`), agrupado por menú.

**Prueba:** `.qa/roles-permisos.js` (admin, bodega, SEO); identidad: `.qa/config-identidad/roles-form.php`
(simulación) y `after-u3.json` (foto tras guardar sin cambios desde el navegador).

**Fuera de alcance / pendiente:** QR (`storage_manager` fijo), informe de envíos, Documentos.

**Verificado (2026-11-13):** `.qa/roles-permisos.js` 70/70 (admin, bodega, SEO); foto por rol idéntica
tras guardar sin cambios; lint en verde. PR #37. El enlace de acción rápida y el de vista previa son
el mismo: el servidor solo lo rechaza si el rol no tiene ninguna de las dos.
**No verificado:** staging; el último commit (el Escritorio se llama «Escritorio», antes «Inicio»)
solo pasó el lint, y la captura del PR aún dice «Inicio».
