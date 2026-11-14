/*
 * WooCommerce › Documentos: the live preview of the PDFs. Asks the endpoint of
 * includes/documents-preview.php for the real document with the values of the form (saved or
 * not), after a short pause, and shows it in the frame. Nothing is saved.
 *
 * Without JavaScript the panel is a plain GET form (button "Actualizar previsualización") and the
 * frame inside <noscript> shows the saved configuration.
 */
(function () {
    'use strict';

    const config = window.jl22Preview;
    const panel = document.querySelector('[data-jl-preview]');
    const settings = document.getElementById('jl-documents-form');
    if (!config || !panel || !settings || !window.fetch || !window.URL || !window.AbortController) {
        return;
    }

    const controls = panel.querySelector('.jl-preview__controls');
    const select = panel.querySelector('#jl-preview-doc');
    const orderField = panel.querySelector('[data-preview-order]');
    const orderInput = panel.querySelector('#jl-preview-order');
    const help = panel.querySelector('#jl-preview-doc-help');
    const status = panel.querySelector('#jl-preview-status');
    const stage = panel.querySelector('.jl-preview__stage');
    const frame = panel.querySelector('.jl-preview__frame:not(.jl-preview__frame--static)');
    const open = panel.querySelector('.jl-preview__open a');
    let timer = 0;
    let controller = null;
    let blobUrl = '';

    panel.classList.add('is-live');

    function checked(name) {
        const input = settings.querySelector('input[name="' + name + '"]:checked');
        return input ? input.value : null;
    }

    /** The query of the form's current values: the endpoint checks each one against its list. */
    function params() {
        const option = select.options[select.selectedIndex];
        const query = new URLSearchParams();
        query.set('doc', select.value);
        const paper = checked('paper_size');
        if (paper) {
            query.set('paper_size', paper);
        }
        const font = settings.querySelector('select[name="font"]');
        const weight = checked('font_weight');
        if (font && weight !== null) {
            query.set('font', font.value);
            query.set('font_weight', weight);
        }
        const order = orderInput.value.trim();
        if (option && option.dataset.needsOrder === '1' && order !== '') {
            query.set('order_id', order);
        }
        return { query: query, option: option, paper: paper };
    }

    function say(text, kind) {
        status.textContent = text;
        status.classList.toggle('is-loading', kind === 'loading');
        status.classList.toggle('is-error', kind === 'error');
    }

    function release() {
        if (blobUrl) {
            URL.revokeObjectURL(blobUrl);
            blobUrl = '';
        }
    }

    async function refresh() {
        if (controller) {
            controller.abort();
        }
        controller = new AbortController();
        const mine = controller;
        const current = params();
        const label = current.option ? current.option.textContent.trim() : '';
        const url = new URL(config.url, window.location.href);
        current.query.forEach(function (value, key) {
            url.searchParams.set(key, value);
        });
        open.href = url.href;
        if (current.paper) {
            stage.dataset.paper = current.paper;
        }
        stage.classList.add('is-busy');
        stage.setAttribute('aria-busy', 'true');
        say(config.messages.loading, 'loading');

        try {
            const request = new URL(url.href);
            request.searchParams.set('ajax', '1');
            const response = await fetch(request.href, { credentials: 'same-origin', signal: mine.signal });
            if (!(response.headers.get('Content-Type') || '').includes('application/pdf')) {
                let message = config.messages.failed;
                try {
                    const data = await response.json();
                    if (data && data.data && data.data.message) {
                        message = data.data.message;
                    }
                } catch (e) {
                    // Not JSON: the generic message.
                }
                throw new Error(message);
            }
            const blob = await response.blob();
            if (mine !== controller) {
                return;
            }
            release();
            blobUrl = URL.createObjectURL(blob);
            frame.src = blobUrl + '#toolbar=0&navpanes=0&view=FitH';
            stage.classList.remove('is-empty');
            say(config.messages.ready.replace('%s', label), '');
        } catch (error) {
            if (mine.signal.aborted) {
                return; // a newer request took over
            }
            stage.classList.add('is-empty');
            say(error instanceof TypeError ? config.messages.network : error.message, 'error');
        } finally {
            if (mine === controller) {
                stage.classList.remove('is-busy');
                stage.removeAttribute('aria-busy');
            }
        }
    }

    function schedule() {
        clearTimeout(timer);
        timer = setTimeout(refresh, config.delay);
    }

    function syncDocument() {
        const option = select.options[select.selectedIndex];
        orderField.hidden = !option || option.dataset.needsOrder !== '1';
        if (option && help) {
            help.textContent = option.dataset.description || '';
        }
    }

    select.addEventListener('change', function () {
        syncDocument();
        schedule();
    });
    orderInput.addEventListener('input', schedule);
    settings.addEventListener('change', schedule);
    controls.addEventListener('submit', function (event) {
        event.preventDefault();
        clearTimeout(timer);
        refresh();
    });
    window.addEventListener('pagehide', release);

    syncDocument();
    refresh();
}());
