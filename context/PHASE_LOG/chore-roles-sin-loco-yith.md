# chore/roles-sin-loco-yith (2026-11-11)

César: quitar Loco Translate a storage y eliminar YITH POS. Migración 005: quita loco_admin de
storage y las 56 caps de YITH POS (yith_pos_*, *_yith-pos-*, access/manage_woocommerce_pos,
manage_woocommerce_point_of_sale) de administrator y seo_manager; borra 27 opciones yith_pos_* y
5 posts (2 tiendas, 2 cajas, 1 recibo) con su meta. Todo recordado en
inversionesjl22_site_migration_005. No se tocan los ~606 meta yith_pos de pedidos (historial).
004 ya no lista esas caps. roles-menu.php sin loco-config-user. Copia: migrate OK, segunda corrida
vacía, menús OK.

Revisión en staging con la sesión real de storage (BODEGA JL), solo lectura salvo una acción
rápida: menú/barra OK; URLs bloqueadas → Escritorio; estados ocultos vacíos; lote solo
"invoice"; informe con controles propios y empresas por AJAX; ficha de Procesando abre, Impreso
→ lista; acción rápida Impreso en #62088 → volvió a la lista con el aviso de estado de pago
(regla OK, sin cambio). Sin probar: escaneo QR (requiere el teléfono).
