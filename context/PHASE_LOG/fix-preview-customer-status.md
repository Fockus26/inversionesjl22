# fix/preview-customer-status (2026-11-11)

La vista previa del pedido siempre mostraba "Estándar": la plantilla Backbone no marcaba la
opción (data.jl_customer_status ya llegaba). Ahora `selected` por plantilla, `<label for>` con id
por pedido, y tras guardar se actualiza data('orderData') del botón de vista previa (wc-orders.js
la guarda toda la carga de la página). Verificado con .qa/vista-previa-status.js (abrir, cambiar,
reabrir, recargar; axe sin violaciones).
