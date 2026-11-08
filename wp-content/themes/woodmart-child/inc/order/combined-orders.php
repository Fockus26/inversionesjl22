<?php
/**
 * JL22 — Preserve billing cedula + barrio + shipping when orders are combined.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ---------------- helpers ---------------- */

if (!function_exists('jl22_resolve_order_id')) {
    function jl22_resolve_order_id($maybe) {
        if (is_numeric($maybe)) return intval($maybe);
        if (is_string($maybe)) {
            $s = trim($maybe);
            if (ctype_digit($s)) return intval($s);
            if (strlen($s) > 1 && ($s[0] === '{' || $s[0] === '[')) {
                $dec = json_decode($s, true);
                if (is_array($dec)) {
                    foreach (['id','ID','post_id'] as $k) {
                        if (!empty($dec[$k])) return intval($dec[$k]);
                    }
                }
            }
        }
        if (is_array($maybe)) {
            foreach (['id','ID','post_id'] as $k) {
                if (!empty($maybe[$k])) return intval($maybe[$k]);
            }
        }
        if (is_object($maybe)) {
            if (method_exists($maybe, 'get_id')) return intval($maybe->get_id());
            foreach (['id','ID','post_id'] as $k) {
                if (isset($maybe->$k)) return intval($maybe->$k);
            }
            $arr = (array) $maybe;
            foreach (['id','ID','post_id'] as $k) {
                if (isset($arr[$k])) return intval($arr[$k]);
            }
        }
        return null;
    }
}

/* ---- CEDULA ---- */
if (!function_exists('jl22_get_cedula_from_order')) {
    function jl22_get_cedula_from_order($order_id) {
        $keys = ['_billing_cedula','billing_cedula'];
        foreach ($keys as $k) {
            $v = get_post_meta($order_id, $k, true);
            if ($v !== '' && $v !== null) return [$k, $v];
        }
        $order = wc_get_order($order_id);
        if ($order) {
            foreach ($keys as $k) {
                $v = $order->get_meta($k);
                if ($v !== '' && $v !== null) return [$k, $v];
            }
        }
        return [null, null];
    }
}
if (!function_exists('jl22_set_cedula_on_order')) {
    function jl22_set_cedula_on_order($order_id, $cedula) {
        if ($cedula === null || $cedula === '') return;
        update_post_meta($order_id, '_billing_cedula', $cedula);
        update_post_meta($order_id, 'billing_cedula',  $cedula);
    }
}

/* ---- BARRIO ---- */
if (!function_exists('jl22_get_barrio_from_order')) {
    function jl22_get_barrio_from_order($order_id) {
        $keys = ['_billing_barrio','billing_barrio'];
        foreach ($keys as $k) {
            $v = get_post_meta($order_id, $k, true);
            if ($v !== '' && $v !== null) return [$k, $v];
        }
        $order = wc_get_order($order_id);
        if ($order) {
            foreach ($keys as $k) {
                $v = $order->get_meta($k);
                if ($v !== '' && $v !== null) return [$k, $v];
            }
        }
        return [null, null];
    }
}
if (!function_exists('jl22_set_barrio_on_order')) {
    function jl22_set_barrio_on_order($order_id, $barrio) {
        if ($barrio === null || $barrio === '') return;
        update_post_meta($order_id, '_billing_barrio', $barrio);
        update_post_meta($order_id, 'billing_barrio',  $barrio);
    }
}

/* ---------------- hook: clone_extra_billing_fields_hook ---------------- */
add_action('clone_extra_billing_fields_hook', function($new, $old) {
    $new_id = jl22_resolve_order_id($new);
    $old_id = jl22_resolve_order_id($old);

    if (empty($new_id) || empty($old_id)) return;

    // Cedula
    $existing_ced = get_post_meta($new_id, '_billing_cedula', true);
    if ($existing_ced === '' || $existing_ced === null) {
        list($_k, $val) = jl22_get_cedula_from_order($old_id);
        if ($val) jl22_set_cedula_on_order($new_id, $val);
    }

    // Barrio
    $existing_bar = get_post_meta($new_id, '_billing_barrio', true);
    if ($existing_bar === '' || $existing_bar === null) {
        list($_k, $val) = jl22_get_barrio_from_order($old_id);
        if ($val) jl22_set_barrio_on_order($new_id, $val);
    }
}, 20, 2);

/* ---------------- hook: wuoc_combined_order_created_hook ---------------- */
add_action('wuoc_combined_order_created_hook', function($new_order_id, $order_ids) {
    $new_id = jl22_resolve_order_id($new_order_id);

    if (is_string($order_ids)) {
        $order_ids = array_filter(array_map('intval', preg_split('/[,\s]+/', $order_ids)));
    } elseif (is_numeric($order_ids)) {
        $order_ids = [ intval($order_ids) ];
    } elseif (!is_array($order_ids)) {
        $order_ids = [];
    }

    if (empty($new_id) || empty($order_ids)) return;

    // Cedula
    $existing_ced = get_post_meta($new_id, '_billing_cedula', true);
    if ($existing_ced === '' || $existing_ced === null) {
        foreach ($order_ids as $oid) {
            list($_k, $v) = jl22_get_cedula_from_order($oid);
            if ($v) { jl22_set_cedula_on_order($new_id, $v); break; }
        }
    }

    // Barrio
    $existing_bar = get_post_meta($new_id, '_billing_barrio', true);
    if ($existing_bar === '' || $existing_bar === null) {
        foreach ($order_ids as $oid) {
            list($_k, $v) = jl22_get_barrio_from_order($oid);
            if ($v) { jl22_set_barrio_on_order($new_id, $v); break; }
        }
    }

    // Shipping (mantienes lo que ya tenías)
    $winner_item = null;
    foreach ($order_ids as $oid) {
        $o = wc_get_order($oid);
        if ($o && $o->get_items('shipping')) {
            $winner_item = reset($o->get_items('shipping'));
            break;
        }
    }
    if ($winner_item) {
        $new_order = wc_get_order($new_id);
        if ($new_order) {
            foreach ($new_order->get_items('shipping') as $iid => $it) {
                $new_order->remove_item($iid);
            }
            $si = new WC_Order_Item_Shipping();
            $si->set_method_title($winner_item->get_name());
            $si->set_method_id($winner_item->get_method_id());
            if (method_exists($si, 'set_instance_id') && $winner_item->get_instance_id()) {
                $si->set_instance_id($winner_item->get_instance_id());
            }
            $si->set_total($winner_item->get_total());
            $si->set_taxes($winner_item->get_taxes());
            $new_order->add_item($si);
            update_post_meta($new_id, '_shipping_company', $winner_item->get_name());
            $new_order->calculate_totals();
            $new_order->save();
        }
    }
}, 20, 2);
