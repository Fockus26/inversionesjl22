# Kit feedback — {{PROJECT_NAME}}

> What the **web-agent-kit** did wrong, too much or too little in this project, to improve
> it. It is not the project's diary: it is material for the kit. Searched with `grep`, not
> read by default. Kit version it started on / was updated to: see `.kit-version`.

## When a row is added (the agent does it, without being asked)

| Type | Signal |
|---|---|
| **Correction** | César corrects something the agent did following the kit ("no, así no", "otra vez el…", reverts a change) |
| **Repetition** | César had to ask for the same thing a second time, or do by hand a step the kit could do |
| **Gap** | The kit did not say what to do and the agent had to improvise or ask something that should be settled |
| **Friction** | A kit step cost a lot for what it gave (tokens, time, ceremony), or an instruction was wrong |
| **Prevention** | A QA report brings a class finding not yet in the kit's `reference/QA-PREVENTION.md` (its "Prevención" row): the rule that would have prevented it while building |

Not logged: agent mistakes the kit already clearly forbids (that is something else), or
preferences of this project alone (they go to `decisions/`).

**If a row for the same problem already exists, it is not duplicated: add 1 to "Times",
update the date and, if it was marked as sent (`→ PR …`), remove the mark** (so the next
send carries it with the new count). What repeats is what is most worth fixing.

## Log

| # | Date | Type | Times | Kit piece | What happened (1 line) | Proposal (1 line) |
|---|---|---|---|---|---|---|
| 1 | 2026-11-07 | Gap | 1 | reference/tech/wordpress-adopt.md §2 | El repo tenía `plugins/` y `themes/` en la raíz (copia de wp-content sin la carpeta); compose, php-lint, pull-site y deploy suponen `wp-content/`; se movió a mano con `git mv` | Que la guía (o new-project.ps1) detecte `plugins/`+`themes/` en la raíz y proponga moverlos a `wp-content/` |
| 2 | 2026-11-07 | Friction | 1 | new-project.ps1 -Stack wordpress | `.env.example` ya existía (y el proyecto usaba `.env`, no `.env.local`): el script lo dejó tal cual y faltaban todas las claves del kit; se fusionaron a mano | Añadir al `.env.example` existente las claves del kit que falten, en vez de saltarlo |
| 3 | 2026-11-07 | Gap | 1 | reference/tech/wordpress-adopt.md §1 | Hosting GoDaddy: César no sabía si había SSH o FTPS y la tabla de accesos no dice cómo averiguarlo por proveedor | Una línea por hosting común (GoDaddy cPanel / Managed WordPress, Hostinger, SiteGround) con dónde se activa SSH/SFTP |
| 4 | 2026-11-07 | Gap | 1 | docker-compose.yml (WordPress) | Un plugin de terceros vendorizado y modificado en el repo (PDF Invoices) no se monta en la copia: solo el plugin del sitio y el tema hijo | Comentar en el compose una línea para "otros plugins del repo" |
| 5 | 2026-11-08 | Gap | 1 | scripts/pull-site.sh (ssh) | GoDaddy Managed WP exige llave Y contraseña, y corta las sesiones largas: el rsync de todo wp-content nunca terminaba la lista de archivos. Se hizo askpass, bajada por piezas con `chunks-done.txt` y reintentos | Llevar al kit la versión por piezas y el askpass de este proyecto |
| 6 | 2026-11-08 | Gap | 1 | scripts/pull-site.sh (ssh) | La verificación nunca llegaba a 0: logs en vivo (`wc-logs`, `logs/`), caché (`litespeed`) y los mu-plugins del host (gdcorp-wordpress) siempre difieren | Excluirlos por defecto (`SKIP_PIECES`) |
| 7 | 2026-11-08 | Gap | 1 | scripts/pull-site.sh (import) | El drop-in `object-cache.php` (Object Cache Pro → Redis del host) tumbaba todo wp-cli en la copia; y el prefijo de tablas no era `wp_` | Desactivar el drop-in al importar (hecho aquí) y proponer `TABLE_PREFIX` leído del volcado |
| 8 | 2026-11-09 | Gap | 1 | docker-compose.yml (WordPress) | En Windows la copia tardaba ~50 s por página: wp-content por bind mount y opcache de 4.000 archivos. Se movió el código a un volumen (`local:sync`) y se subió opcache | Llevarlo al compose del kit; y la imagen wordpress:cli antepone `wp` cuando un plugin corta wp-cli (`entrypoint: /usr/bin/env`) |
| 9 | 2026-11-09 | Gap | 1 | new-project.ps1 -Stack wordpress | El CLAUDE.md del kit nombra `bun run build:css` y el plugin trae `assets/scss/site.scss`, pero no hay script `build:css` ni `sass` en devDependencies | Añadir `build:css` (sass) al `package.json` de WordPress |
| 10 | 2026-11-09 | Gap | 1 | git-flow (modo pr) / orchestrate | Una cadena de PRs encadenados (#5→#14) se mergeó cada uno en su base: solo #5 llegó a main, las unidades 2–11 quedaron en ramas intermedias y hubo que abrir un PR de integración (#15) | Que git-flow, al abrir un PR encadenado, avise el orden de merge (del último al primero, o cambiar la base a main antes de mergear) y que al cerrar compruebe que lo mergeado llegó a main |
| 11 | 2026-11-09 | Correction | 1 | git-flow (cierre de unidad) | César: "cuando termines una unidad no te quedes quieto, busca terminar todo lo que queda pendiente"; el agente paraba a preguntar tras cada PR | En modo pr, tras abrir el PR seguir con la siguiente unidad del plan y juntar las preguntas para César al final |
| 12 | 2026-11-11 | Gap | 1 | wordpress-adopt.md / deploy-ssh.yml | Staging (clon viejo de GoDaddy) tenía PDF Invoices 3.2.3 sin `custom/` y datos hasta 01-2026, mientras producción tiene la 5.16.6 vendorizada: César vio el informe sin estilo, sin datos y la acción en lote rota, y lo tomó como fallo del código | Antes del primer deploy a staging, comparar versiones de plugins y archivos vendorizados staging vs producción (o refrescar staging desde producción), y que el deploy suba también los plugins vendorizados o avise de la diferencia |
| 13 | 2026-11-11 | Correction | 1 | wordpress-adopt.md / CLAUDE.md (UI) | Para el estilo de los formularios el agente sacó colores de las opciones de Woodmart y propuso 3 opciones; César: "revisa lo del artifact", el canvas de diseño del proyecto ya tenía el sistema visual y las pantallas de admin | Que el CLAUDE.md del proyecto (o DESIGN_RULES) enlace el artifact de diseño y que la regla "nueva decisión visual → 3 opciones" diga que primero se mira el diseño ya aprobado |
| 14 | 2026-11-12 | Silent | 1 | scripts/pr-screenshots.ts | `bun run shots` falla si el Chromium de Playwright no está instalado ("Executable doesn't exist"); los scripts de QA del proyecto usan el canal msedge y sí funcionan. Se tomaron con un script propio (`.qa/shots-edge.js`) | Que pr-screenshots.ts acepte `PW_CHANNEL` (o pruebe msedge/chrome si falta Chromium) |
| 15 | 2026-11-12 | Twice | 1 | CLAUDE.md / DESIGN_RULES (proyecto) | César pidió el botón primario con texto blanco en el informe (PR #31) y el agente volvió a hacerlo con texto oscuro en la pantalla siguiente: la preferencia no quedó en decisions/ ni en DESIGN_RULES | Que una corrección visual de César a un componente compartido (botón, campo) se anote como decisión del proyecto en el mismo PR, no solo en el CSS de esa pantalla |

## Sending it to the kit

When César asks for it (or when closing phase 09): the **`kit-feedback`** skill
(`/kit-feedback`). It builds the report with the unsent rows, grouped by kit piece with the
most repeated first, and opens a PR on the kit's repo with the `kit-feedback` label. Sent
rows get marked with `→ PR <owner/repo#N>` in "Proposal" (they are not deleted).
