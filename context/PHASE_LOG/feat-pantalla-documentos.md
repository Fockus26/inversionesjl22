# feat/pantalla-documentos — Fase 3, unidad 5: WooCommerce › Documentos

**Base:** `main` 98b086f (#36, #37 y #38 ya fusionados).

**Pedido (César, 2026-11-13):** «El tamaño por default 80mm, pero que pueda ser configurable».

**Qué:** `config['documents']['paper_size']` (`80mm` de fábrica, `58mm`). `inversionesjl22_site_document_papers()`
(lista cerrada con anchos en pt) y `inversionesjl22_site_document_paper()` (el tamaño en uso, cae a 80 mm
si falta o no es válido) en config.php. Pantalla `includes/settings-documents.php` (tercera pestaña,
radios en fieldset, guardar / restaurar solo esta sección, sin JS).

**Dónde se aplica:**
- Factura: `custom/inversionesjl-pdf.php` (plugin vendorizado), filtro `wpo_wcpdf_paper_format` toma el
  ancho de `inversionesjl22_site_document_paper()` e ignora `paper_size` de PDF Invoices; el alto
  automático sigue igual (detecta el ancho). Más un bloque `wpo_wcpdf_custom_styles` solo a 58 mm.
  Ambos marcados `JL22` para reaplicar al actualizar el plugin.
- Informe de envíos: `shipping-report/pdf.php` (ancho) y `pdf-ui.php` (cambia `size: 80mm auto` del CSS
  del plugin vendorizado, que no se toca).

**Decisiones:**
- Sin plantilla `Factura58mm`: bastó CSS condicional (cuerpo 54 mm, letra menor, etiqueta sobre valor en
  las tablas de datos del cliente y de envío, columnas de productos reajustadas, QR de 18 mm).
- El ajuste `paper_size` de PDF Invoices queda sin efecto mientras el plugin propio esté activo.
- 58 mm = 162 pt (medida que ya tenía la tabla del plugin vendorizado).

**Siguientes candidatos (no añadidos):** «Tipografía» y «Grosor de la tipografía» (`jl_template_font`,
`jl_template_font_weight` en los ajustes de PDF Invoices) encajarían en esta pantalla.

**Prueba:** `.qa/documentos.js`; rasterizar PDFs: `.qa/pdf-raster.js` (pdf.js desde cdnjs).
