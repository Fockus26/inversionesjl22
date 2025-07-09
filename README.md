# WooCommerce Customizations - Mayorista Cosméticos

Este repositorio contiene el código personalizado utilizado en el sitio de venta al mayor de productos cosméticos desarrollado con WordPress + WooCommerce. Incluye ajustes al theme child, lógica de roles personalizados, mejoras en la administración de pedidos y modificaciones a plugins de terceros.

---

## 📦 Contenido del repositorio

- **functions.php**  
  Código con funciones clave:
  - Asignación automática de SKU según categoría.
  - Control de acciones de pedido según el rol de usuario (`seo_manager`, `storage_manager`).
  - Restricciones sobre los estados de pedido y acciones masivas.
  - Ocultamiento de la factura PDF y opciones de edición para ciertos roles.
  - Integración con códigos de barra de YITH.

- **plugin-modificado/**  
  Carpeta que contiene la versión modificada del plugin usado para generar facturas PDF. Incluye adaptaciones para los requerimientos específicos del negocio.
---

## 🔐 Roles personalizados
- `Ambos roles`
  No pueden cambiar el estado en pedidos cancelados

- `seo_manager`  
  Puede ver y cambiar pedidos entre "En espera" y "Procesando", pero no completarlos ni borrarlos.

- `storage_manager`  
  Solo puede completar pedidos si están en "Procesando", y no puede ver la factura PDF si el pedido está "En espera".
---

## 🧩 Plugins relacionados

- **WooCommerce PDF Invoices & Packing Slips**  
  Usado para generar facturas PDF. Se ha modificado para limitar accesos según el estado del pedido y el rol.
---

## 🚀 Instalación

1. Clona este repositorio en la carpeta `wp-content/themes/tu-child-theme/`.
2. Coloca el plugin modificado en `wp-content/plugins/`.
3. Activa el theme y el plugin desde el admin de WordPress.
4. Asigna los roles personalizados a los usuarios correspondientes.

---

## 📌 Notas

- Requiere el theme base **Woodmart**.
- Se recomienda usar este código en un entorno controlado o de staging antes de pasarlo a producción.

---

## 📄 Licencia

Este código está diseñado para uso interno. No se distribuye públicamente como plugin o theme.
