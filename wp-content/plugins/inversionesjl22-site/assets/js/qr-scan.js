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
 * - 2026-11-12: el aviso es un snackbar abajo al centro, no una capa que tapa toda la pantalla.
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

/*
 * The scan's result as a snackbar (2026-11-12, César): at the bottom, centred, over the page
 * instead of covering the whole screen. Dark with white text (14:1); the order status's colour
 * is only a decorative band, the message says what happened. It stays 6 s, longer while the
 * pointer is on it or it has the focus, and has a close button. Screen readers hear it from a
 * live region that exists before the message arrives (a region created along with its text is
 * often not announced).
 */
const QR_SNACKBAR_MS = 6000;

function qrScanSnackbarSetup() {
    if (document.getElementById('qr-scan-live')) {
        return;
    }
    const style = document.createElement('style');
    style.id = 'qr-scan-snackbar-style';
    style.textContent =
        '#qr-scan-notification{position:fixed;z-index:99999;left:50%;bottom:calc(24px + env(safe-area-inset-bottom,0px));' +
        'box-sizing:border-box;display:flex;align-items:center;gap:14px;width:max-content;min-width:min(360px,calc(100vw - 32px));' +
        'max-width:calc(100vw - 32px);padding:10px 8px 10px 0;overflow:hidden;font-family:inherit;font-size:18px;font-weight:600;' +
        'line-height:1.35;color:#fff;background:#2b2a35;border-radius:14px;box-shadow:0 10px 30px rgba(43,42,53,.28);' +
        'opacity:0;transform:translate(-50%,16px);transition:opacity .25s ease,transform .25s ease}' +
        '#qr-scan-notification.is-visible{opacity:1;transform:translate(-50%,0)}' +
        '#qr-scan-notification .qr-scan-band{align-self:stretch;flex:0 0 8px;margin:-10px 0}' +
        '#qr-scan-notification .qr-scan-text{flex:1 1 auto;min-width:0;padding:6px 0}' +
        '#qr-scan-notification .qr-scan-close{flex:0 0 auto;display:grid;place-items:center;width:44px;height:44px;margin:0;padding:0;' +
        'font:inherit;font-size:24px;line-height:1;color:#fff;background:transparent;border:0;border-radius:10px;cursor:pointer}' +
        '#qr-scan-notification .qr-scan-close:hover{background:rgba(255,255,255,.12)}' +
        '#qr-scan-notification .qr-scan-close:focus-visible{outline:2px solid #fff;outline-offset:-4px}' +
        '#qr-scan-live{position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0}' +
        '@media (prefers-reduced-motion:reduce){#qr-scan-notification{transition:none;transform:translate(-50%,0)}}';
    document.head.appendChild(style);

    const live = document.createElement('div');
    live.id = 'qr-scan-live';
    live.setAttribute('role', 'status');
    document.body.appendChild(live);
}

function showQrScanNotification(message, color) {
    qrScanSnackbarSetup();

    const snackbar = document.createElement('div');
    snackbar.id = 'qr-scan-notification';

    const band = document.createElement('span');
    band.className = 'qr-scan-band';
    band.setAttribute('aria-hidden', 'true');
    band.style.background = color || '#ffb16e';

    const text = document.createElement('span');
    text.className = 'qr-scan-text';
    text.textContent = message;

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'qr-scan-close';
    close.setAttribute('aria-label', 'Cerrar aviso');
    close.innerHTML = '<span aria-hidden="true">&times;</span>';

    snackbar.appendChild(band);
    snackbar.appendChild(text);
    snackbar.appendChild(close);
    document.body.appendChild(snackbar);

    const live = document.getElementById('qr-scan-live');
    live.textContent = '';
    setTimeout(function () {
        live.textContent = message;
    }, 100);

    requestAnimationFrame(function () {
        requestAnimationFrame(function () {
            snackbar.classList.add('is-visible');
        });
    });

    let timer = 0;
    let gone = false;

    function hide() {
        if (gone) {
            return;
        }
        gone = true;
        clearTimeout(timer);
        snackbar.classList.remove('is-visible');
        const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        setTimeout(function () {
            snackbar.remove();
        }, reduced ? 0 : 300);
    }

    function start() {
        clearTimeout(timer);
        timer = setTimeout(hide, QR_SNACKBAR_MS);
    }

    function pause() {
        clearTimeout(timer);
    }

    snackbar.addEventListener('mouseenter', pause);
    snackbar.addEventListener('mouseleave', function () {
        if (!snackbar.contains(document.activeElement)) {
            start();
        }
    });
    snackbar.addEventListener('focusin', pause);
    snackbar.addEventListener('focusout', function (event) {
        if (!snackbar.contains(event.relatedTarget)) {
            start();
        }
    });
    close.addEventListener('click', hide);
    document.addEventListener('keydown', function onKey(event) {
        if (gone) {
            document.removeEventListener('keydown', onKey);
        } else if (event.key === 'Escape') {
            hide();
        }
    });

    start();
}
