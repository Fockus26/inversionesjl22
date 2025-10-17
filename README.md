# 🛒 InversionesJL22 – WooCommerce Customizations

A set of customizations for WooCommerce tailored to client-specific needs, including invoice PDF modifications, role-based order workflows, and automation via QR codes.

---

## 🌍 Overview

This project involved customizing WooCommerce for a Colombian client to enhance their store management and operational workflows.  

Key aspects included:
- Custom invoice PDF templates (size, layout, extra data)  
- QR-based order completion workflow for storage managers  
- Granular role-based permissions controlling visibility and actions  
- Custom reports in PDF  
- SKU auto-generation for new products  
- Excel/PDF exports  

---

## ✨ Features

- 🧾 Custom invoice templates for 80mm thermal printers  
- 📊 Exportable order reports in PDF and Excel  
- 🔐 Role-based restrictions (visibility, actions, state transitions)  
- 📦 SKU auto-generator for new products  
- 📱 QR integration: scan → update order status automatically  
- 🚫 Restrictions: no order trashing, limited state changes per role  
- ⚡ AJAX-based workflow updates without page reload  

---

## 🛠 Tech Stack

- **Platform:** WordPress + WooCommerce  
- **Language:** PHP  
- **Reports:** WooCommerce PDF Invoices & Packing Slips (customized)  
- **Frontend:** HTML, CSS, JS (WordPress admin + templates)  
- **Export:** FPDF / Excel (WooCommerce integration)  

---

## 📂 Project Structure

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

## 📖 Case Study

This WooCommerce customization was delivered to a Colombian client requiring enhanced control over store operations. Modifications optimized invoice printing, enforced strict role-based workflows, and enabled real-time order completion through QR codes.

---

## 📈 Future Improvements

- 🔗 API endpoints for order and report management
- 📱 Mobile-friendly dashboard for storage managers
- 🧪 Unit tests for role and state logic

---

## 📜 License

This project is licensed under a private agreement with the client and is **not open-source**.
