/*
 * WPCodeBox > PDF > "Scan Qr_1" (id 62) — JS, Frontend Header.
 *
 * Cambios respecto a la versión anterior:
 * - Vuelve a consultar cada 3 segundos (como la versión original del plugin), así la transición aparece
 *   en cualquier pantalla del sitio abierta con storage_manager cuando se escanea un pedido desde otro equipo.
 *   La versión anterior consultaba una sola vez al cargar la página.
 * - La consulta no usa caché del navegador.
 */
function checkQrScanJs(ajaxUrl) {
    if (!ajaxUrl) {
        return;
    }

    let busy = false;

    async function poll() {
        // No apilar consultas ni mostrar dos transiciones a la vez
        if (busy || document.getElementById('qr-scan-notification')) {
            return;
        }
        busy = true;

        try {
            const response = await fetch(`${ajaxUrl}?action=check_qr_scan&_=${Date.now()}`, {
                cache: 'no-store',
                credentials: 'same-origin',
            });

            const rawText = await response.text();

            let result;

            try {
                result = JSON.parse(rawText);
            } catch (jsonError) {
                console.error('❌ Error parseando JSON:', jsonError);
                return;
            }

            if (!result.success || !result.data || !result.data.scanned) {
                return;
            }

            showQrScanNotification(result.data.message, result.data.color);
        } catch (e) {
            console.error('💥 Error en fetch o ejecución:', e);
        } finally {
            busy = false;
        }
    }

    poll();
    setInterval(poll, 3000);
}

function showQrScanNotification(message, color) {
    const overlay = document.createElement('div');
    overlay.id = 'qr-scan-notification';

    Object.assign(overlay.style, {
        position: 'fixed',
        inset: '0',
        background: color.replace('rgb', 'rgba').replace(')', ', 0.8)'),
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        zIndex: '99999',
        opacity: '0',
        transition: 'opacity .4s ease'
    });

    // Crear texto centrado
    const text = document.createElement('div');
    text.textContent = message;

    Object.assign(text.style, {
        fontSize: '48px',
        fontWeight: '700',
        color: '#000',
        textAlign: 'center',
        padding: '20px'
    });

    overlay.appendChild(text);
    document.body.appendChild(overlay);

    // Fade in
    setTimeout(() => {
        overlay.style.opacity = '1';
    }, 50);

    // Fade out después de 3.5s
    setTimeout(() => {
        overlay.style.opacity = '0';

        setTimeout(() => {
            overlay.remove();
        }, 400);

    }, 3500);
}
