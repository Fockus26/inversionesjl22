<?php
$global_flow = [
    'reserved'   => 'on-hold',
    'processing' => ['reserved', 'on-hold'],
    'impressed'  => 'processing',
    'completed'  => 'impressed',
    'cancelled'  => ['on-hold', 'reserved', 'processing'],
];

$userPermissions = [];

function setUserPermissions($username, $ruleset) {
    global $userPermissions;
    $userPermissions[$username] = $ruleset;
}

function getUserPermissions() {
    global $userPermissions;
    if (current_user_can('administrator')) return null;

    $user = wp_get_current_user();
    foreach ($user->roles as $role) {
        if (isset($userPermissions[$role])) return $userPermissions[$role];
    }
    return null;
}

setUserPermissions('seo_manager', [
  'on-hold' => true,
  'reserved' => true,
  'processing' => true,
  'impressed' => true,
  'completed' => true,
  'cancelled' => true,
  
  // Exclusiones por tipo de acción
  'exclude_status_actions' => [
    'quick_actions' => ['impressed', 'completed', 'cancelled'],
    'bulk_actions' => ['impressed', 'completed'],
    'order_preview_actions' => ['impressed', 'completed', 'cancelled'],
    'order_view' => ['cancelled'],
    'order_view_actions' => ['impressed', 'cancelled'],
    'qr_actions' => ['impressed', 'completed', 'cancelled']
  ],

  // Además del flujo global, puede devolver pedidos de procesando a en espera
  'flow' => array_merge($global_flow, [
    'on-hold' => 'processing',
  ]),
]);

setUserPermissions('storage_manager', [
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
