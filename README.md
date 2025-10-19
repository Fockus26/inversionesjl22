# 🛒 InversionesJL22 – Case Study on WooCommerce Customizations

Advanced WooCommerce customizations for invoice automation, QR-based workflows, and role-based store management.

---

## 🌍 Overview

This case study demonstrates how WooCommerce can be extended to meet specific business requirements. The project included:

- Custom invoice templates for thermal printers
- QR-based order completion flow
- Granular role-based permissions for actions and visibility
- Exportable reports (PDF/Excel)
- SKU auto-generation for new products
- AJAX-based workflow updates and restrictions

These enhancements improved security, efficiency, and operational control for a Colombian client’s WooCommerce store.

---

## ✨ Features

- 🧾 **80mm Invoice Template** – Custom WooCommerce invoice template designed for 80mm thermal printers. Displays full order, shipping, and payment details along with a scannable QR code for storage validation.
- 📦 **Automatic SKU Generator** – Automatically assigns SKUs to new orders or products without one. SKUs are dynamically generated based on the selected category, or a default format if no category is assigned.
- 💫 **Conditional Pay System** – Adds a custom payment status selector visible only for four “home delivery” shipping methods. Allows choosing between Paid or To Collect.
  - When “To Collect” is selected, it enables an amount input and currency selector (COP/USD).
  - These details are reflected in the invoice PDF under the shipping section.
  - Orders cannot transition to another status unless a payment condition is selected.
- 📱 **QR Scanning Workflow** – Allows users with the **Storage Manager** role to update order statuses (e.g., Printed → Completed) by scanning the QR code on the invoice — no admin navigation required.
- 🧾 **Shipping Report Generator** – Adds a WooCommerce submenu to generate PDF shipping reports.
  - Filter by country, shipping method, and date range.
  - Produces a thermal-format PDF (80mm) showing all order IDs for the selected method, including each order’s shipping cost.
- 🔐 **Role-Based Restrictions** – Defines strict access rules for specific user roles:

  - **Storage Manager:** Can view and manage orders in Processing and Printed states only.
  - **SEO Manager:** Can view and manage orders in Pending, Reserved, and Completed states only.

  These limits apply to quick actions, bulk actions, preview visibility, and edit access, ensuring that each role interacts only with relevant orders.

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
     ├── functions.php
     └── inc/
         ├── integrations/
         │   ├── admin-notices.php
         │   ├── enqueue.php
         │   ├── open-graph.php
         │   ├── sku-generator.php
         │   └── yith.php
         ├── order/
         │   ├── change-shipping-method.php
         │   ├── combined-orders.php
         │   ├── custom-status.php
         │   ├── payment-status.php
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
