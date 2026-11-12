/*
 * Order preview (orders list) › "Status del Cliente": saves the select on change.
 * Moved from the inline script of includes/customer-status.php (WPCodeBox › User › Custom
 * Status, snippet 64). The nonce comes from wp_localize_script (jlCustomerStatus.nonce).
 */
jQuery(function ($) {
    $(document).on('change', '.jl-customer-status-select', function () {
        const select = $(this);
        const orderId = select.data('order-id');
        if (!orderId) {
            return;
        }

        select.prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'jl_update_customer_status',
                order_id: orderId,
                status: select.val(),
                nonce: window.jlCustomerStatus ? window.jlCustomerStatus.nonce : ''
            },
            success: function (response) {
                if (!response.success) {
                    alert((response.data && response.data.message) || 'No se pudo actualizar el status.');
                    return;
                }
                // WooCommerce keeps each preview's data for the page's life (wc-orders.js,
                // data('orderData')): update it, or reopening shows the old status.
                const cached = $('.order-preview[data-order-id="' + orderId + '"]').data('orderData');
                if (cached) {
                    cached.jl_customer_status = response.data.status;
                }
            },
            error: function () {
                alert('Ocurrió un error al actualizar el status.');
            },
            complete: function () {
                select.prop('disabled', false);
            }
        });
    });
});
