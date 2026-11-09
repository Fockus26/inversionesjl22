# Project Context — InversionesJL22

> Adoptado al kit 3.19.0 el 2026-11-07 (`/kit-update`, caso adopción WordPress). No es un
> proyecto nacido con `project-kickoff`: el sitio ya existe y está en producción.
> Lo que todavía no se sabe dice `PENDIENTE`.

## Summary

Tienda WooCommerce de un cliente colombiano. Lo propio del proyecto es la operación interna:
facturas para impresora térmica con QR, cierre de pedidos escaneando el QR en bodega, reporte
de envíos en PDF y permisos estrictos por rol sobre los pedidos.

## Project type

e-commerce (WordPress + WooCommerce), sitio existente adoptado.

## Business

- **What it does:** vende productos en línea en Colombia, con envíos a domicilio y retiro.
- **What the site must achieve:** vender, y que bodega y SEO operen pedidos sin errores.
- **Main action:** comprar; internamente, imprimir la factura y cerrar el pedido por QR.

## Target audience

Clientes finales en Colombia (y el equipo interno: storage_manager en bodega, seo_manager).

## Language and market

- Language(s): Spanish
- Country / locale: Colombia · es-CO
- Currency and format: COP (y USD en el cobro contraentrega)

## Stack

- **WordPress** 7.1.4 · PHP 8.3.36 · MySQL 8.4 (producción y ensayo en PHP 8.3) · page builder: ninguno; tema
  Woodmart (premium) + hijo `wp-content/themes/woodmart-child`
- **Plugins clave:** WooCommerce PENDIENTE · WooCommerce PDF Invoices & Packing Slips 5.16.6,
  vendorizado en `wp-content/plugins/woocommerce-pdf-invoices-packing-slips` y **modificado**
  (`custom/`, `templates/Factura80mm/`, `templates/Factura58mm/`) · WPCodeBox 2 (24 snippets,
  respaldo en `wpcodebox/` con `manifest.json`) · YITH (integración en el tema hijo)
- **Premium plugins:** Woodmart, WPCodeBox 2, PENDIENTE el resto (local copy: Docker from the backup)
- **Hosting access:** SSH/SFTP · panel: GoDaddy WordPress administrado 2.0, plan Deluxe (1 sitio,
  20 GB; el sitio usa ~11 GB). CDN de GoDaddy activa además de Cloudflare. WP-CLI, rsync y
  mysqldump disponibles por SSH; raíz de WordPress `html`. wp-content ~6 GB, base ~620 MB
  (185 tablas, prefijo `wp_`).
  - **Acceso SSH:** llave **y** contraseña ("publickey ... partial success"). `pull-site.sh` pasa
    la contraseña con `SSH_ASKPASS` desde `PULL_SSH_PASSWORD`. GoDaddy corta las sesiones
    largas: wp-content se baja por carpetas (`.site-pull/chunks-done.txt` guarda lo verificado).
  - Los plugins emiten avisos de PHP 8.3 en cada `wp` por SSH (user-role-editor,
    woocommerce-multi-currency, floating cart): `wp db export` va con `--skip-plugins --skip-themes`.
  - Producción: SSH `892841.us32.ssh.myftpupload.com` · dominio temporal `892841.us32.myftpupload.com`
    (antes `vx0.93c.myftpupload.com`, cambiado por GoDaddy en 2026-11)
  - Ensayo (staging): SSH `985270.us32.ssh.myftpupload.com` · URL PENDIENTE
  - Usuarios SSH: en el panel ("Ver o cambiar"); van en `.env.local`, nunca aquí
- **Deploy:** deploy-ssh → staging (sitio de ensayo) → production
- **Site plugin:** `wp-content/plugins/inversionesjl22-site` (esqueleto del kit; recibe los
  snippets de WPCodeBox) · ACF: no se usa (PENDIENTE confirmar)
- **CDN:** Cloudflare delante del sitio; cachea el HTML anónimo `max-age=2678400`
- Package manager for the JS tooling: **bun**

## Repo and deployment

- Repo: https://github.com/Fockus26/inversionesjl22 (private)
- Versioning: no versioning (el `Version:` del plugin del sitio)
- Production: https://inversionesjl22.com
- Local `context/` backup: branch `context-state` (private repo) — PENDIENTE el primer `context:backup`

## Functionality beyond static

- [x] Cart / checkout (WooCommerce)
- [x] Payments (pasarela PENDIENTE de inventariar)
- [x] Emails the app sends (los de WooCommerce)
- [x] Roles con permisos propios: storage_manager (Processing, Printed), seo_manager
      (Pending, Reserved, Completed)
- [x] Estados de pedido propios (Printed, Reserved) y transiciones con validaciones
- [x] Factura 80/58 mm con QR; cierre por QR
- [x] Reporte de envíos PDF por país, método y rango de fechas
- [x] Generador de SKU por categoría

## Particulars

- Al actualizar el plugin PDF hay que reaplicar `custom/`, las plantillas y el `include_once`
  en `includes()`; la fuente de verdad de las plantillas es la copia de producción.
- Los hooks de QR, filtros de IVA/cédula y el PDF del reporte viven hoy en WPCodeBox, no en
  `template-functions.php`: no duplicarlos al migrar.
