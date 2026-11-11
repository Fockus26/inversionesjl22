# feat/roles-sin-ure (2026-11-11)

User Role Editor Pro solo usaba su módulo "admin menu access" (storage: permitir 5 entradas;
seo: bloquear 28). Front-end menus vacío; nav-menus apuntaba a un rol inexistente
(`wpseo_manager`). Reemplazado por includes/roles-menu.php (listas permitidas por rol: menú,
pantallas sin menú, nodos de la barra; lo no listado → Escritorio, también por URL) y la
migración 004 (capacidades de los 2 roles en código; crea/repone, no quita; desactiva URE, no lo
borra).

Verificado en la copia con cuentas QA locales (qa-storage, qa-seo en .env.test.local; las de
.env.local no entran en la copia): menús idénticos a con URE (.qa/menu-con-ure.json vs
menu-plugin.json), URLs bloqueadas → Escritorio, acción rápida vuelve a la lista (con URE iba al
Escritorio), ver pedido OK, admin sin restricción, migrate 004 OK e idempotente.
Cambios a propósito: sin "Añadir" en la barra de seo (daba 403 también con URE) y con el botón
"Menú" del móvil (URE lo quitaba). Storage sigue viendo Loco Translate › Ajustes como antes.
