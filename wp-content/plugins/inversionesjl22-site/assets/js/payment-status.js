/*
 * Order › Estado de pago: currency and amount only for "Por cobrar".
 * Moved as is from WPCodeBox › Order › Payment Status_1 (snippet 46). Loaded by
 * includes/payment-status.php.
 */
function togglePaymentInfo() {
    const statusSelect = document.querySelector('#payment-status');
    const infoDiv = document.querySelector('#payment-info');

    if (statusSelect && infoDiv) {
        statusSelect.addEventListener('change', function() {
            if (this.value === 'pending') {
                infoDiv.style.display = 'block'; 
            } else {
                infoDiv.style.display = 'none';
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', togglePaymentInfo);
