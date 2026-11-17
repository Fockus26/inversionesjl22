/*
 * WPCodeBox > PDF > "Scan Qr_1" (id 62) — JS, Frontend Header.
 *
 * Cambios respecto a la versión anterior:
 * - Vuelve a consultar cada 3 segundos (como la versión original del plugin), así la transición aparece
 *   en cualquier pantalla del sitio abierta con un rol que tenga el QR (por defecto storage_manager) cuando
 *   se escanea un pedido desde otro equipo.
 *   La versión anterior consultaba una sola vez al cargar la página.
 * - La consulta no usa caché del navegador.
 * - 2026-11-11: pide los escaneos posteriores al último que vio (since) en vez de uno solo que la
 *   primera pantalla borraba; así el aviso sale en todas las pantallas de bodega.
 * - 2026-11-12: el aviso es un snackbar abajo al centro, no una capa que tapa toda la pantalla.
 * - 2026-11-13: lo carga cualquier rol con el QR (Inversiones JL22 › Roles y permisos), no solo bodega.
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
 * The scan's result as a snackbar (2026-11-12, César, option B): a pill at the bottom, centred,
 * over the page instead of covering the whole screen, filled with the order status's colour
 * (the colours qr-scan.php sends, all light) and dark text #2b2a35 (7:1 or more on every one
 * of them); a QR icon, and the message says what happened, so the colour is not the only cue.
 * It stays 6 s, longer while the pointer is on it or it has the focus, and has a close button.
 * Screen readers hear it from a live region that exists before the message arrives (a region
 * created along with its text is often not announced).
 */
const QR_SNACKBAR_MS = 6000;
const QR_SNACKBAR_ICON = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' +
    '<rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/>' +
    '<path d="M7 17v.01M14 14h3M20 14v.01M14 14v3M14 20h3M17 17h3M20 17v3"/></svg>';

function qrScanSnackbarSetup() {
    if (document.getElementById('qr-scan-live')) {
        return;
    }
    const style = document.createElement('style');
    style.id = 'qr-scan-snackbar-style';
    style.textContent =
        '#qr-scan-notification{position:fixed;z-index:99999;left:50%;bottom:calc(24px + env(safe-area-inset-bottom,0px));' +
        'box-sizing:border-box;display:flex;align-items:center;gap:12px;width:max-content;max-width:calc(100vw - 32px);' +
        'padding:8px 8px 8px 20px;font-family:inherit;font-size:18px;font-weight:600;line-height:1.35;color:#2b2a35;' +
        'background:#ffb16e;border:1px solid rgba(43,42,53,.18);border-radius:999px;box-shadow:0 10px 30px rgba(43,42,53,.22);' +
        'opacity:0;transform:translate(-50%,16px);transition:opacity .25s ease,transform .25s ease}' +
        '#qr-scan-notification.is-visible{opacity:1;transform:translate(-50%,0)}' +
        '#qr-scan-notification svg{flex:0 0 auto}' +
        '#qr-scan-notification .qr-scan-text{flex:1 1 auto;min-width:0;padding:6px 0}' +
        '#qr-scan-notification .qr-scan-close{flex:0 0 auto;display:grid;place-items:center;width:44px;height:44px;margin:0;padding:0;' +
        'font:inherit;font-size:24px;line-height:1;color:#2b2a35;background:rgba(43,42,53,.1);border:0;border-radius:50%;cursor:pointer}' +
        '#qr-scan-notification .qr-scan-close:hover{background:rgba(43,42,53,.2)}' +
        '#qr-scan-notification .qr-scan-close:focus-visible{outline:2px solid #2b2a35;outline-offset:2px}' +
        '#qr-scan-live{position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0}' +
        '@media (max-width:480px){#qr-scan-notification{border-radius:24px;padding-left:16px}}' +
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
    if (color) {
        snackbar.style.background = color;
    }
    snackbar.insertAdjacentHTML('afterbegin', QR_SNACKBAR_ICON);

    const text = document.createElement('span');
    text.className = 'qr-scan-text';
    text.textContent = message;

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'qr-scan-close';
    close.setAttribute('aria-label', 'Cerrar aviso');
    close.innerHTML = '<span aria-hidden="true">&times;</span>';

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
