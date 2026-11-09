# InversionesJL22

Tienda WooCommerce de un cliente colombiano con facturas 80/58 mm, flujo de QR en bodega y
permisos por rol (storage_manager, seo_manager). Producción: https://inversionesjl22.com (tras Cloudflare).

**Type:** e-commerce · **Client:** InversionesJL22 · **Git mode:** pr
(see `context/GIT_STATE.md`) · **Kit:** web-agent-kit, version in `.kit-version` (`/kit-update`)
**Hosting:** GoDaddy WordPress administrado (Deluxe), SSH/SFTP · **Deploy:** deploy-ssh →
staging (sitio de ensayo de GoDaddy) → production

**Language:** talk to César in Spanish — questions, reports, PR titles and bodies, commit
descriptions. Site copy in Spanish. Code and comments: like the code around them.

---

## What to read — by levels, not everything

**Always:** this file + `context/CURRENT_PHASE.md` (one screen: what is open and what blocks).

**Per task** — only the row that applies:

| If the task… | Also read |
|---|---|
| touches PHP, templates, assets, data or the deploy | the kit's `reference/tech/wordpress.md` (the section it touches) |
| brings the site down again or rebuilds the local copy | `reference/tech/wordpress-adopt.md` § 3 |
| touches UI | `context/DESIGN_RULES.md` (and the theme's `theme.json` / the builder's global styles) |
| opens or closes a unit | `git-flow` skill + `context/GIT_STATE.md` |
| arrives with an orchestrator prompt | **only** what the prompt cites |

**Search, don't read:** `decisions/`, `CONTENT_CHECKLIST.md`, `PHASE_LOG/`, `plans/` — `grep`
the word or ID and open only what comes up.

**The kit's `reference/…`** = `~/.claude/kit-reference/…` (or `.claude/kit-reference/…`).
**Tech refs:** wordpress → its `tech/<name>.md` when the task touches it; only these.

---

## Stack

- **WordPress** 7.1.4 · PHP 8.3 · tema Woodmart (premium) + hijo `woodmart-child` ·
  WooCommerce · WooCommerce PDF Invoices & Packing Slips 5.16.6 **vendorizado y modificado**
  (`custom/`, `templates/Factura80mm|58mm/`: reaplicar en cada actualización) · WPCodeBox 2
- **All the code in the plugin** `wp-content/plugins/inversionesjl22-site/` (ACF in its `acf-json/`),
  presentation in `wp-content/themes/woodmart-child/`. **No snippet plugins**, ever: los 24
  snippets de WPCodeBox (respaldo en `wpcodebox/`) se mudan al plugin, uno por commit.
- **Local copy:** Docker from the backup (`docker-compose.yml`) at
  production's versions · `http://localhost:8080` · email in Mailpit `http://localhost:8025`
- **bun** for the JS tooling (Playwright, sass) — never npm, yarn or pnpm

```bash
bun run local:up · local:down        # the copy (bun run local:wp -- <wp-cli command>)
bun run lint:php                     # php -l (Docker if PHP is not installed)
bun run build:css                    # SCSS → the committed CSS
bun run site:pull -- <ssh|ftps|archive|dbs|import>   # bring production down (.site-pull/)
bun run shots                        # PR screenshots; then `shots:publish`
bun run context:backup               # local context/ → branch context-state (context:restore)
```

**Local copy and QA:** the agents start and use the local copy; never production. Playwright
scripts run with `node` (Bun hangs on Windows), in `.qa/`. QA agents point at the copy's URL.

**Docs:** Context7 before any WordPress, ACF or builder API. **Model per task:** `Modelo
recomendado: <model · effort>` before each (kit `TOKEN-ECONOMY.md` §12; risk → `opus`).

---

## How work is done here

One unit at a time (a feature of the plugin, a template, a form, a bounded fix), something
reviewable in two minutes. One unit = one branch = one PR.

```
[git-flow: open]  →  implement in the plugin  →  check it in the local copy  →  [a11y]
   →  lint:php + build:css  →  screenshots (if the UI changed)
   →  prune and back up context/  →  [git-flow: close → PR (or pause + local merge)]
```

- **`pr` mode:** commit and push your branch without asking — César approves in the PR
  review. **Never** push to `main`, `gh pr merge` or auto-merge. A merge deploys to staging.
- **Cloud session** (`CLAUDE_CODE_REMOTE=true`): `cloud-session` skill at start and close.
- **`local` mode:** no commit without César's explicit approval (`git-flow` pause).
- **Version:** the plugin's `Version:` header and its constant, bumped in the PR that ships a
  release. No Changesets.
- **Data changes are migrations** in the plugin's `migrations/` (`tech/wordpress.md` §3), never
  clicks in an admin. Without SSH they run on the first wp-admin visit after the deploy.
- Nothing destructive: no `reset --hard`, `push --force`, history rewriting or deleting
  branches. Never edit production (files, database or admin) directly.

### What goes in the repo and what is local

In git: `.kit-version`, the plugin (with `acf-json/` and its compiled CSS), the child theme,
`docker-compose.yml` or `.wp-env.json`, `scripts/`, `.env.example`, `.github/`, and `context/`
(PROJECT_CONTEXT, DESIGN_RULES, decisions/). Local (gitignored): `.site-pull/` (production's
files and dumps: **customers' personal data**), `.env.local` (access), (CURRENT_PHASE,
CONTENT_CHECKLIST, KIT_FEEDBACK, GIT_STATE, PHASE_LOG/, plans/) (backed up with `context:backup`).

### Quality gates

`a11y` whenever UI is touched (page builders: `tech/wordpress.md` §4) · `seo` through the SEO
plugin when a page with content closes · `git-flow` opening and closing each unit ·
`functional-qa` when a risk flow closes (forms, checkout) · `design-qa` at the version QA.
Before the first deploy: the full walk-through of `wordpress-adopt.md` § 6.

---

## Non-negotiable rules

- **Security on every input:** nonce, capability check, sanitize in, escape out,
  `$wpdb->prepare` (`tech/wordpress.md` §4).
- **Zero secrets in the code.** `.env.local` and GitHub secrets; `.env.example` without values.
- **Zero invented content.** Flagged placeholder + row in `context/CONTENT_CHECKLIST.md`.
- **WCAG 2.1 AA** minimum: 4.5:1 text, visible focus, no state only by color, no horizontal
  scroll at 320 px.
- **PHP of the oldest host version** (`Requires PHP`): no syntax newer than it.
- **Roles:** cada cambio en pedidos respeta lo que ven y hacen storage_manager (Processing,
  Printed) y seo_manager (Pending, Reserved, Completed); probar con sus cuentas de prueba.

## When to stop and ask

- New visual decision → 3 options with real pros and cons, and wait.
- Anything that touches production (deploy by hand, a plugin update, a setting) → César.
- Accessibility rule broken → the rule wins. Problem in closed code → report it, not fixed in
  this unit. Unused file → point it out, **do not delete it**.

## Kit feedback

César corrects what you did following the kit, asks for something twice, does by hand what
the kit could do, or the kit is silent → a row in `context/KIT_FEEDBACK.md` (or +1 in
"Times"), without asking, announced in one line. `/kit-feedback` sends it to the kit.

---

## Project context

**Audience:** clientes en Colombia y el equipo de la tienda (bodega, SEO) · **Main action:**
comprar; internamente, imprimir la factura y cerrar el pedido por QR · **Language:** es-CO

**Particulars:**
- Cloudflare cachea el HTML anónimo (`max-age=2678400`): causó el falso "logout" al escanear QR.
- Moneda COP y USD (cobro contraentrega en el estado de pago).
