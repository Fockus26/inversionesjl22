# 🛒 InversionesJL22 – WooCommerce Customizations

**English**

A set of customizations for WooCommerce tailored to the specific needs of a client. The project involved modifying the WooCommerce PDF Invoices & Packing Slips plugin, enhancing the order workflow in WordPress Admin, and implementing role-based restrictions and automations.

**Español**

Un conjunto de personalizaciones para WooCommerce adaptadas a las necesidades específicas de un cliente. El proyecto incluyó modificaciones al plugin WooCommerce PDF Invoices & Packing Slips, mejoras en el flujo de trabajo de pedidos en WordPress Admin, e implementación de restricciones y automatizaciones basadas en roles.

---

## 🌍 Overview / Descripción

**English**

The project required customizing the invoice PDF output (size, layout, and extra data), adding a QR-based workflow to complete orders when scanned by a storage manager, and implementing granular role-based permissions to control visibility and available actions in WooCommerce. Additionally, it included custom reports in PDF, SKU auto-generation, and Excel/PDF exports.

**Español**

El proyecto requirió personalizar la salida de facturas en PDF (tamaño, diseño y datos adicionales), añadir un flujo basado en QR para completar pedidos cuando eran escaneados por el rol de storage manager, e implementar un sistema granular de permisos para controlar la visibilidad y las acciones disponibles en WooCommerce. Además, se incluyeron reportes PDF personalizados, generación automática de SKU, y exportaciones en Excel/PDF.

---

## ✨ Features / Características

**English**

- 🧾 Custom invoice templates for 80mm thermal printers

- 📊 Order reports exportable in PDF and Excel

- 🔐 Role-based restrictions (visibility, actions, state transitions)

- 📦 SKU auto-generator for new products

- 📱 QR integration: scan → update order status automatically

- 🚫 Restrictions: no order trashing, limited state changes per role

- ⚡ AJAX-based workflow updates without reload

**Español**

- 🧾 Plantillas de facturas personalizadas para impresoras térmicas de 80mm

- 📊 Reportes de pedidos exportables en PDF y Excel

- 🔐 Restricciones basadas en roles (visibilidad, acciones, transiciones de estado)

- 📦 Generador automático de SKU para productos nuevos

- 📱 Integración con QR: escaneo → cambio automático de estado del pedido

- 🚫 Restricciones: sin acceso a papelera, transiciones limitadas por rol

- ⚡ Actualizaciones de flujo vía AJAX sin recarga

---

## 🛠️ Tech Stack / Tecnologías

- **Platform:** WordPress + WooCommerce

- **Language:** PHP

- **Reports:** WooCommerce PDF Invoices & Packing Slips (customized)

- **Frontend:** HTML, CSS, JS (WordPress admin + templates)

- **Export:** FPDF / Excel (WooCommerce integration)

---

## 📂 Project Structure / Estructura del Proyecto
```text
plugins/
 └── woocommerce-pdf-invoices-packing-slips
themes/
 └── woodmart-child/
     ├── templates/
     │   └── Factura80mm/   # Custom invoice template
     ├── functions.php
     ├── styles.css
     └── inc/
         ├── integrations/
         │   ├── enqueue.php
         │   ├── sku-generator.php
         │   └── yith.php
         ├── order/
         │   ├── change-shipping-method.php
         │   ├── custom-status.php
         │   └── transition-status.php
         ├── report/
         │   ├── html-shipping.php
         │   └── pdf-shipping.php
         └── roles/
             ├── config.php
             └── order-visibility.php
```

---

## 📖 Case Study / Estudio de Caso

**English**

This customization was delivered to a client in Colombia who required improved control over their WooCommerce store. The modifications streamlined invoice printing, enforced strict role-based workflows, and allowed real-time order completion via QR codes.

**Español**

Esta personalización fue entregada a un cliente en Colombia que necesitaba un mayor control en su tienda WooCommerce. Las modificaciones optimizaron la impresión de facturas, reforzaron flujos de trabajo basados en roles y permitieron la finalización de pedidos en tiempo real mediante códigos QR.

---

## 📈 Future Improvements / Mejoras Futuras

**English**

- 🔗 API endpoints for order and report management

- 📱 Mobile-friendly dashboard for storage managers

- 🧪 Unit tests for role and state logic

**Español**

- 🔗 Endpoints API para gestión de pedidos y reportes

- 📱 Dashboard optimizado para móviles (storage managers)

- 🧪 Pruebas unitarias para roles y lógica de estados

---

## 📜 License / Licencia

**English**

This project is licensed under a private agreement with the client and is not open-source.

**Español**

Este proyecto está licenciado bajo un acuerdo privado con el cliente y no es de código abierto.
