<?php
/**
 * ============================
 * USER PERMISSIONS CONFIG FILE
 * ============================
 */

limit_user_permissions('storage_manager', [
    'to_processing'  => false,
    'to_impressed'   => true,
    'to_completed'   => true,
    'to_cancelled'   => false,
    'can_view_order' => false,
    'filter_invoice_status' => ['on-hold', 'cancelled', 'completed']
]);

limit_user_permissions('seo_manager', [
    'to_processing'  => true,
    'to_impressed'   => false,
    'to_completed'   => false,
    'to_cancelled'   => true,
    'can_view_order' => true,
    'filter_invoice_status' => []
]);
