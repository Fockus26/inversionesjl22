<?php
/**
 * ============================
 * USER PERMISSIONS CONFIG FILE
 * ============================
 *
 * Define las reglas de permisos y visibilidad para cada rol de usuario
 * usados en la lógica de control de acceso y acciones sobre pedidos.
 */

// ============================================
// 🔁 FLUJO GLOBAL DE TRANSICIÓN DE ESTADOS
// ============================================

$global_flow = [
    'reserved'   => 'on-hold',
    'processing' => ['reserved', 'on-hold'],
    'impressed'  => 'processing',
    'completed'  => 'impressed',
    'cancelled'  => ['on-hold', 'reserved', 'processing'],
];

// Permisos para SEO Manager
limit_user_permissions('seo_manager', [
  'on-hold' => true,
  'reserved' => true,
  'processing' => true,
  'impressed' => false,
  'completed' => true,
  'cancelled' => true,
  
  // Exclusiones por tipo de acción
  'exclude_status_actions' => [
    'quick_actions' => ['impressed', 'completed'],
    'bulk_actions' => ['completed', 'wuoc_combine'],
    'order_preview_actions' => ['completed'],
    'order_view' => [],
    'order_view_actions' => [],
    'qr_actions' => ['completed']
  ],

  'flow' => $global_flow,
]);

// Permisos para Storage Manager
limit_user_permissions('storage_manager', [
  'on-hold'     => false,
  'reserved'    => false,   
  'processing'  => true,   
  'impressed'   => true,    
  'completed'   => true,   
  'cancelled'   => false,   
  
  'exclude_status_actions' => [
    'quick_actions' => ['completed'],
    'bulk_actions' => ['completed', 'wuoc_combine'],
    'order_preview_actions' => ['completed'],
    'order_view' => ['impressed'],
    'order_view_actions' => ['impressed'],
    'qr_actions' => ['completed']
  ],

  'flow' => $global_flow,
]);
