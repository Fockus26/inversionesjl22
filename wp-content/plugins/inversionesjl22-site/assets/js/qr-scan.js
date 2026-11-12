/*
 * WPCodeBox > PDF > "Scan Qr_1" (id 62) — JS, Frontend Header.
 *
 * Cambios respecto a la versión anterior:
 * - Vuelve a consultar cada 3 segundos (como la versión original del plugin), así la transición aparece
 *   en cualquier pantalla del sitio abierta con storage_manager cuando se escanea un pedido desde otro equipo.
 *   La versión anterior consultaba una sola vez al cargar la página.
 * - La consulta no usa caché del navegador.
 * - 2026-11-11: pide los escaneos posteriores al último que vio (since) en vez de uno solo que la
 *   primera pantalla borraba; así el aviso sale en todas las pantallas de bodega.
 */
function checkQrScanJs(ajaxUrl) {
    if (!ajaxUrl) {
        return;
    }

    // Server time of the last poll: the next one asks for the scans after it. Kept per tab, so
    // a reload (or the redirect that follows a scan) neither repeats nor misses one.
    const KEY = 'jlQrScanSince';
    let since = 0;
    try {
        since = parseFloat(sessionStorage.getItem(KEY)) || 0;
    } catch (e) {
        since = 0;
    }
    // The device that scanned lands on ?qr_scan_done=<server time of the scan>: from there, so
    // it sees its own scan however long the page took to load.
    const scannedAt = parseFloat(new URLSearchParams(location.search).get('qr_scan_done'));
    if (scannedAt && (!since || since > scannedAt)) {
        since = scannedAt - 1;
    }
    let busy = false;

    async function poll() {
        // No apilar consultas ni mostrar dos transiciones a la vez
        if (busy || document.getElementById('qr-scan-notification')) {
            return;
        }
        busy = true;

        try {
            const response = await fetch(`${ajaxUrl}?action=check_qr_scan&since=${since}&_=${Date.now()}`, {
                cache: 'no-store',
                credentials: 'same-origin',
            });

            const result = JSON.parse(await response.text());
            if (!result.success || !result.data) {
                return;
            }

            if (result.data.now) {
                since = result.data.now;
                try {
                    sessionStorage.setItem(KEY, String(since));
                } catch (e) {
                    // Private mode: this tab just keeps it in memory.
                }
            }

            const events = result.data.events || [];
            if (events.length) {
                // Several scans in 3 s: the last one is what the screen needs to show.
                const last = events[events.length - 1];
                showQrScanNotification(last.message, last.color);
            }
        } catch (e) {
            console.error('Error consultando escaneos QR:', e);
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
