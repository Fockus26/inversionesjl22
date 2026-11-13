# Inversiones JL22 — Tienda

Plugin con todo el código propio de la tienda de InversionesJL22 (WooCommerce). Reemplaza los
snippets de WPCodeBox: el código vive aquí, en git.

## Qué hace

- **Estados y flujo de los pedidos:** estados propios con color e icono, y a qué estado puede
  pasar cada uno.
- **Permisos por rol:** qué ve y qué hace cada rol sobre los pedidos (bodega, SEO y los roles
  que se creen), y qué roles pueden cerrar pedidos escaneando el QR.
- **Facturas en PDF** (80 mm o 58 mm) con QR, generadas por el plugin.
- **Informe de envíos en PDF** por día, país, empresa de envío y horario.
- **Cierre de pedidos por QR** en bodega.

## Pantallas de configuración

Todas están en **WooCommerce** (menú de wp-admin) y las ve quien tenga el permiso
`manage_options` (administradores). Comparten pestañas arriba:

| Pantalla | Para qué |
|---|---|
| **Estados y flujo** | Editar los estados del pedido, su color e icono y el flujo entre ellos. |
| **Roles y permisos** | Matriz de permisos por rol, crear roles y asignar el escaneo QR a un rol. |
| **Documentos** | Tamaño del papel (80/58 mm), tipografía y grosor de las facturas y del informe. |

La configuración se guarda en la opción `inversionesjl22_site_config`.

## Volver a los valores iniciales

Cada pantalla tiene su botón **Restaurar** (valores iniciales de esa pantalla; en Estados y
flujo pide confirmación y no restaura si hay pedidos en un estado que dejaría de existir).
Para dejar todo de fábrica de una vez, con WP-CLI:

```bash
wp option delete inversionesjl22_site_config
```

## Requisitos

- WordPress 7.1 o superior y PHP 7.4 o superior.
- WooCommerce 11.2 o superior (probado con 11.2). El plugin lo exige (`Requires Plugins`).

## Para quien programa

- Cambios de datos: migraciones numeradas en `migrations/` (se ejecutan una vez cada una, por
  el contador `inversionesjl22_site_db_version`, no por la versión del plugin).
- Los assets usan la fecha del archivo como versión de caché (`inversionesjl22_site_asset_version()`).
- La versión es la cabecera `Version:` y la constante `INVERSIONESJL22_SITE_VERSION`; se suben
  juntas en el PR que publica una versión.
- No se renombran la carpeta, el prefijo `inversionesjl22_site_`, las constantes ni el text
  domain `inversionesjl22-site`: dependen de ellos las opciones guardadas y el deploy.

## Registro de cambios

### 1.0.0

- Configuración única de estados, flujo y reglas por rol (#33).
- Pantalla **Estados y flujo**, con color e icono por estado y acciones según el flujo (#34–#36).
- Pantalla **Roles y permisos**, con crear roles y QR asignable por rol (#37, #38).
- Pantalla **Documentos**: papel de 80 o 58 mm (#39).
- Dompdf propio e informe de envíos con él; factura propia sin PDF Invoices (#40–#42).
- Identidad propia del plugin y versión 1.0.0.
