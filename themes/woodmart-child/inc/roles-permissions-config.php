<?php
/**
 * ============================
 * USER PERMISSIONS CONFIG FILE
 * ============================
 *
 * Define las reglas de permisos y visibilidad para cada rol de usuario
 * usados en la lógica de control de acceso y acciones sobre pedidos.
 */

// Permisos para SEO Manager
limit_user_permissions('seo_manager', [
    'to_processing'  => true,    // Puede marcar pedidos como "processing"
    'to_impressed'   => false,   // No puede marcar como "impreso"
    'to_completed'   => false,   // No puede completar pedidos
    'to_cancelled'   => true,    // Puede cancelar pedidos
    'can_view_order' => true,    // Puede ver los pedidos en admin
    'filter_invoice_status' => [],  // No hay estados que oculten botón factura
    'extra_view_status' => ['completed'] // Ver también pedidos completados en lista
]);

// Permisos para Storage Manager
limit_user_permissions('storage_manager', [
    'to_processing'  => false,   // No puede marcar "processing"
    'to_impressed'   => true,    // Puede marcar "impreso"
    'to_completed'   => true,    // Puede completar pedidos
    'to_cancelled'   => false,   // No puede cancelar pedidos
    'can_view_order' => false,   // No puede ver pedidos en admin
    'filter_invoice_status' => ['on-hold', 'completed', 'cancelled'], // Oculta botón factura en estos estados
    'extra_view_status' => []    // Sin estados adicionales para ver
]);
