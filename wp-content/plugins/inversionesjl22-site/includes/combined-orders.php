<?php
/**
 * Orders combined with "WooCommerce Ultimate Order Combination": the new order gets the
 * cédula, the barrio and the shipping line of the orders it was made from (the plugin does not
 * copy them).
 *
 * Moved as is from WPCodeBox › Order › Combined Orders (snippet 44).
 */

defined( 'ABSPATH' ) || exit;

function resolveOrderId($id) {
    if (is_numeric($id)) return intval($id);
    if (is_string($id)) {
        $string = trim($id);
        if (ctype_digit($string)) return intval($string);
        if (strlen($string) > 1 && ($string[0] === '{' || $string[0] === '[')) {
            $dec = json_decode($string, true);
            if (is_array($dec)) {
                foreach (['id','ID','post_id'] as $key) {
                    if (!empty($dec[$key])) return intval($dec[$key]);
                }
            }
        }
    }
    if (is_array($id)) {
        foreach (['id','ID','post_id'] as $key) {
            if (!empty($id[$key])) return intval($id[$key]);
        }
    }
    if (is_object($id)) {
        if (method_exists($id, 'get_id')) return intval($id->get_id());
        foreach (['id','ID','post_id'] as $key) {
            if (isset($id->$key)) return intval($id->$key);
        }
        $array = (array) $id;
        foreach (['id','ID','post_id'] as $key) {
            if (isset($array[$key])) return intval($array[$key]);
        }
    }
    return null;
}
function getOrderField($order_id, $field) {
    $keys = ['_' . $field, $field];
    foreach ($keys as $key) {
        $value = get_post_meta($order_id, $key, true);
        if ($value !== '' && $value !== null) return [$key, $value];
    }
    $order = wc_get_order($order_id);
    if ($order) {
        foreach ($keys as $key) {
            $value = $order->get_meta($key);
            if ($value !== '' && $value !== null) return [$key, $value];
        }
    }
    return [null, null];
}
function setOrderField($order_id, $field, $data) {
    if ($data === null || $data === '') return;
    update_post_meta($order_id, '_' . $field, $data);
    update_post_meta($order_id, $field, $data);
}

/* ---------------- hook: clone_extra_billing_fields_hook ---------------- */
add_action('clone_extra_billing_fields_hook', 'cloneExtraCombinedOrderFields', 10, 2);
function cloneExtraCombinedOrderFields($new, $old) {
    $newId = resolveOrderId($new);
    $oldId = resolveOrderId($old);

    if (empty($newId) || empty($oldId)) return;

    // Cedula
    $cedulaField = 'billing_cedula';
    $cedula = get_post_meta($newId, '_' . $cedulaField, true);
    if ($cedula === '' || $cedula === null) {
        list($_key, $value) = getOrderField($oldId, $cedulaField);
        if ($value) setOrderField($newId, $cedulaField, $value);
    }

    // Barrio
    $neighborhoodField = 'billing_barrio';
    $neighborhood = get_post_meta($newId, '_' . $neighborhoodField, true);
    if ($neighborhood === '' || $neighborhood === null) {
        list($_key, $value) = getOrderField($oldId, $neighborhoodField);
        if ($value) setOrderField($newId, $neighborhoodField, $value);
    }
}

/* ---------------- hook: wuoc_combined_order_created_hook ---------------- */
add_action('wuoc_combined_order_created_hook', 'combinedOrderFields', 10, 2);
function combinedOrderFields($newOrderId, $orderIds) {
    $newId = resolveOrderId($newOrderId);

    if (is_string($orderIds)) {
        $orderIds = array_filter(array_map('intval', preg_split('/[,\s]+/', $orderIds)));
    } elseif (is_numeric($orderIds)) {
        $orderIds = [ intval($orderIds) ];
    } elseif (!is_array($orderIds)) {
        $orderIds = [];
    }

    if (empty($newId) || empty($orderIds)) return;

    // Cedula
    $cedulaField = 'billing_cedula';
    $cedula = get_post_meta($newId, '_' . $cedulaField, true);
    if ($cedula === '' || $cedula === null) {
        foreach ($orderIds as $oid) {
            list($_key, $value) = getOrderField($oid, $cedulaField);
            if ($value) { setOrderField($newId, $cedulaField, $value); break; }
        }
    }

    // Barrio
    $neighborhoodField = 'billing_barrio';
    $neighborhood = get_post_meta($newId, '_' . $neighborhoodField, true);
    if ($neighborhood === '' || $neighborhood === null) {
        foreach ($orderIds as $oid) {
            list($_key, $value) = getOrderField($oid, $neighborhoodField);
            if ($value) { setOrderField($newId, $neighborhoodField, $value); break; }
        }
    }

    // Shipping (mantienes lo que ya tenías)
    $items = null;
    foreach ($orderIds as $oid) {
        $oorder = wc_get_order($oid);
        if ($oorder && $oorder->get_items('shipping')) {
            $items = reset($oorder->get_items('shipping'));
            break;
        }
    }
    if ($items) {
        $newOrder = wc_get_order($newId);
        if ($newOrder) {
            foreach ($newOrder->get_items('shipping') as $iid => $it) {
                $newOrder->remove_item($iid);
            }
            $shipping = new WC_Order_Item_Shipping();
            $shipping->set_method_title($items->get_name());
            $shipping->set_method_id($items->get_method_id());
            if (method_exists($shipping, 'set_instance_id') && $items->get_instance_id()) {
                $shipping->set_instance_id($items->get_instance_id());
            }
            $shipping->set_total($items->get_total());
            $shipping->set_taxes($items->get_taxes());
            $newOrder->add_item($shipping);
            update_post_meta($newId, '_shipping_company', $items->get_name());
            $newOrder->calculate_totals();
            $newOrder->save();
        }
    }
}
