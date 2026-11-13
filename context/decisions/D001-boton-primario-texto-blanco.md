# D001 · Colors · El botón primario lleva texto blanco sobre terracota #a4521a, no texto oscuro sobre durazno #ffb16e · Implemented

**Decision:** En todo el proyecto (plugin, tema hijo, wp-admin y mockups nuevos) el botón primario es
fondo terracota `#a4521a` con texto blanco `#ffffff`; al pasar el cursor, `#4a4959` con texto blanco.
El durazno `#ffb16e` queda para superficies y acentos que no llevan texto blanco encima.
**Why:** César no quiere el texto oscuro del diseño sobre el naranja: lo pidió en el informe de envíos
(PR #31, 2026-11-11) y de nuevo, para todo el proyecto, el 2026-11-12. Blanco sobre `#ffb16e` da
1.78:1 y no pasa WCAG AA (4.5:1); sobre `#a4521a` da 5.53:1. El naranja más claro que pasa con
blanco es aproximadamente `#b85a1c` (4.65:1).
**Discarded alternative:** texto blanco sobre el durazno del diseño (no pasa contraste; la regla de
accesibilidad manda) y texto oscuro `#2b2a35` sobre durazno (pasa, 7.9:1, pero César lo descartó).
