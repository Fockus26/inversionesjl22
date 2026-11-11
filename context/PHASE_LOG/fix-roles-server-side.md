# fix/roles-server-side (2026-11-11)

Las reglas de roles-config.php ahora se validan en el servidor (includes/roles-enforce.php,
inversionesjl22_site_role_can_move): endpoints orderTo* (order-transitions.php),
woocommerce_mark_order_status (prioridad 1, antes de Woo), lote (woocommerce_bulk_action_ids y
el lote propio de order-statuses.php) y guardado de la ficha (woocommerce_process_shop_order_meta
prio 1 → vuelve al estado actual). Origen deshabilitado para el rol = no se mueve. QR sin tocar.

Verificado en staging: matriz por rol con wp-cli (storage/seo/admin), 403 en orderToCompleted y
mark_order_status para storage, lote filtrado, ficha revertida; clic real de storage
Procesando→Impreso (#62085) OK. Pedidos de prueba: #62085 quedó en Impreso.
