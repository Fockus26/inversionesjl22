/**
 * WooCommerce › Estados y flujo (includes/settings-statuses.php).
 *
 * - Ordenar los estados: flechas (teclado y táctil) y arrastrar por el asa (ratón). El orden que
 *   se guarda es el de las tarjetas en el formulario.
 * - Plegar el panel de edición de cada estado (sin JS van todos abiertos).
 * - "+ Añadir": muestra los destinos sin marcar de "Puede pasar a" (sin JS se ven todos).
 * - Vista del flujo: diagrama por etapas con flechas, dibujado desde el formulario en vivo.
 * - Confirmación antes de restaurar los valores iniciales.
 */
(function () {
    'use strict';

    var root = document.querySelector('.jl-settings');
    var list = root && root.querySelector('[data-jl-sortable]');
    if (!list) {
        return;
    }
    var text = window.inversionesjl22SettingsStatuses || {};
    var live = root.querySelector('[data-jl-live]');

    root.classList.add('has-js');

    function cards() {
        return Array.prototype.slice.call(list.querySelectorAll('[data-jl-status]'));
    }

    // ------------------------------------------------------------ orden

    function refreshMoves() {
        var all = cards();
        all.forEach(function (card, index) {
            card.querySelector('[data-jl-move="up"]').disabled = index === 0;
            card.querySelector('[data-jl-move="down"]').disabled = index === all.length - 1;
        });
        scheduleFlowmap();
    }

    function announce(card) {
        var all = cards();
        if (!live || !text.moved) {
            return;
        }
        live.textContent = text.moved
            .replace('%1$s', card.getAttribute('data-name'))
            .replace('%2$d', all.indexOf(card) + 1)
            .replace('%3$d', all.length);
    }

    var reduceMotion = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
    var SLIDE_MS = 200;

    /**
     * Cambia el orden de las tarjetas con la técnica FLIP: mide dónde estaban, aplica el cambio
     * (mutate), mide dónde quedan y desliza cada una desde su sitio anterior al nuevo. Parte de
     * la posición visual actual, así que un movimiento nuevo a mitad de otro continúa suave.
     * Con prefers-reduced-motion: reduce solo aplica el cambio.
     */
    function flip(mutate) {
        var all = cards();
        if (!all.length || (reduceMotion && reduceMotion.matches)) {
            mutate();
            return;
        }
        var first = all.map(function (card) { return card.getBoundingClientRect().top; });
        mutate();
        all.forEach(function (card) {
            card.style.transition = 'none';
            card.style.transform = '';
        });
        var moving = [];
        all.forEach(function (card, index) {
            var delta = first[index] - card.getBoundingClientRect().top;
            if (Math.abs(delta) > 1) {
                card.style.transform = 'translateY(' + delta + 'px)';
                moving.push(card);
            }
        });
        if (!moving.length) {
            return;
        }
        void list.offsetHeight; // fija la posición de partida antes de animar
        moving.forEach(function (card) {
            card.style.transition = 'transform ' + SLIDE_MS + 'ms ease-out';
            card.style.transform = '';
            window.setTimeout(function () {
                if (card.style.transform === '') {
                    card.style.transition = '';
                }
            }, SLIDE_MS + 50);
        });
    }

    /** La tarjeta que se mueve con las flechas se eleva (sombra y escala) mientras se desliza. */
    function lift(card) {
        if (reduceMotion && reduceMotion.matches) {
            return;
        }
        card.classList.add('is-moved');
        window.clearTimeout(card.jlLiftTimer);
        card.jlLiftTimer = window.setTimeout(function () {
            card.classList.remove('is-moved');
        }, SLIDE_MS + 120);
    }

    list.addEventListener('click', function (event) {
        var button = event.target.closest('[data-jl-move]');
        if (!button) {
            return;
        }
        var card = button.closest('[data-jl-status]');
        var up = button.getAttribute('data-jl-move') === 'up';
        var other = up ? card.previousElementSibling : card.nextElementSibling;
        if (!other) {
            return;
        }
        flip(function () {
            list.insertBefore(up ? card : other, up ? other : card);
        });
        lift(card);
        refreshMoves();
        // La flecha pulsada puede haber quedado deshabilitada (primera o última posición).
        (button.disabled ? card.querySelector('[data-jl-move]:not(:disabled)') : button).focus();
        announce(card);
    });

    var dragged = null;

    list.addEventListener('dragstart', function (event) {
        var handle = event.target.closest && event.target.closest('[data-jl-handle]');
        if (!handle) {
            event.preventDefault();
            return;
        }
        var card = handle.closest('[data-jl-status]');
        dragged = card;
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', card.getAttribute('data-name'));
        // La imagen que sigue al puntero se toma ahora: elevada (sombra y escala). Un instante
        // después la tarjeta de la lista pasa a ser el hueco de destino (discontinuo y atenuado).
        card.classList.add('is-lifting');
        event.dataTransfer.setDragImage(card, 20, 20);
        window.setTimeout(function () {
            card.classList.remove('is-lifting');
            if (dragged === card) {
                card.classList.add('is-dragging');
            }
        }, 0);
    });

    list.addEventListener('dragover', function (event) {
        if (!dragged) {
            return;
        }
        event.preventDefault();
        var over = event.target.closest('[data-jl-status]');
        if (!over || over === dragged) {
            return;
        }
        // Se decide con la posición de diseño (sin la transformación de la animación en curso),
        // para que una tarjeta que aún se desliza no haga oscilar el orden.
        var pointer = event.clientY - list.getBoundingClientRect().top;
        var after = pointer > over.offsetTop + over.offsetHeight / 2;
        var ref = after ? over.nextElementSibling : over;
        if (ref === dragged || ref === dragged.nextElementSibling) {
            return;
        }
        flip(function () {
            list.insertBefore(dragged, ref);
        });
    });

    list.addEventListener('drop', function (event) {
        if (dragged) {
            event.preventDefault();
        }
    });

    list.addEventListener('dragend', function () {
        if (!dragged) {
            return;
        }
        dragged.classList.remove('is-lifting', 'is-dragging');
        refreshMoves();
        announce(dragged);
        dragged = null;
    });

    refreshMoves();

    // ------------------------------------------------------------ panel de edición

    function setOpen(card, open) {
        var toggle = card.querySelector('[data-jl-toggle]');
        var panel = card.querySelector('[data-jl-panel]');
        panel.hidden = !open;
        card.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.querySelector('[data-jl-toggle-text]').textContent = open ? text.close : text.edit;
    }

    cards().forEach(function (card) {
        setOpen(card, false);
    });

    list.addEventListener('click', function (event) {
        var toggle = event.target.closest('[data-jl-toggle]');
        if (toggle) {
            setOpen(toggle.closest('[data-jl-status]'), toggle.getAttribute('aria-expanded') !== 'true');
        }
    });

    // Un campo obligatorio vacío dentro de un panel plegado: el navegador no puede enfocarlo
    // para avisar. Se abre el panel antes.
    root.querySelector('#jl-statuses-form').addEventListener('invalid', function (event) {
        var card = event.target.closest('[data-jl-status]');
        if (card) {
            setOpen(card, true);
        }
    }, true);

    // ------------------------------------------------------------ "puede pasar a"

    function refreshNext(group) {
        var add = group.querySelector('[data-jl-add]');
        var final = group.querySelector('[data-jl-final]');
        var boxes = Array.prototype.slice.call(group.querySelectorAll('input[type="checkbox"]'));
        var checked = boxes.filter(function (box) { return box.checked; }).length;
        var adding = group.classList.contains('is-adding');
        add.hidden = adding || checked === boxes.length;
        add.setAttribute('aria-expanded', adding ? 'true' : 'false');
        if (final) {
            final.hidden = checked > 0 || adding;
        }
    }

    Array.prototype.forEach.call(root.querySelectorAll('[data-jl-next]'), function (group) {
        refreshNext(group);
        group.addEventListener('change', function () {
            refreshNext(group);
        });
        group.querySelector('[data-jl-add]').addEventListener('click', function () {
            group.classList.add('is-adding');
            refreshNext(group);
            var first = group.querySelector('input[type="checkbox"]:not(:checked)');
            if (first) {
                first.focus();
            }
        });
    });

    // ------------------------------------------------------------ vista del flujo (diagrama)
    //
    // Se dibuja desde el formulario (no desde lo guardado): al reordenar, marcar destinos o
    // cambiar nombre, color o icono se redibuja. La distribución en etapas es la misma que
    // calcula inversionesjl22_site_flow_stages() en PHP, que es lo que se ve sin JavaScript.

    var map = root.querySelector('[data-jl-flowmap]');
    var stagesEl = map && map.querySelector('[data-jl-flowmap-stages]');
    var canvas = map && map.querySelector('[data-jl-flowmap-canvas]');
    var svg = map && map.querySelector('.jl-flowmap__arrows');
    var SVG_NS = 'http://www.w3.org/2000/svg';
    var flow = { ready: false, queued: false, width: 0, hover: null, focus: null, arrows: [] };

    /** Lo que dice el formulario ahora: una entrada por tarjeta, en su orden, sin las que se van a eliminar. */
    function readModel() {
        var model = [];
        cards().forEach(function (card) {
            var remove = card.querySelector('input[name$="[delete]"]');
            if (remove && remove.checked) {
                return;
            }
            var label = card.querySelector('input[name$="[label]"]');
            var color = card.querySelector('input[name$="[color]"]:checked');
            var icon = card.querySelector('input[name$="[icon]"]:checked');
            model.push({
                slug: card.getAttribute('data-slug'),
                name: (label && label.value.trim()) || card.getAttribute('data-default-name') || card.getAttribute('data-name'),
                color: color ? color.value : '',
                icon: icon ? icon.value : '',
                next: Array.prototype.map.call(card.querySelectorAll('[data-jl-next] input[type="checkbox"]:checked'), function (box) {
                    return box.value;
                })
            });
        });
        // Los destinos, en el orden de los estados (como inversionesjl22_site_flow_destinations()).
        model.forEach(function (status) {
            status.next = model.filter(function (other) {
                return other.slug !== status.slug && status.next.indexOf(other.slug) !== -1;
            }).map(function (other) { return other.slug; });
        });
        return model;
    }

    /** Qué es cada estado en el flujo y en qué etapa y columna va (ver inversionesjl22_site_status_kind). */
    function layoutFlow(model) {
        var next = {};
        var origins = {};
        model.forEach(function (status) {
            next[status.slug] = status.next;
            origins[status.slug] = origins[status.slug] || [];
            status.next.forEach(function (to) {
                (origins[to] = origins[to] || []).push(status.slug);
            });
        });
        var slugs = model.map(function (status) { return status.slug; });

        function reach(from, without) {
            var seen = {};
            var stack = from.slice();
            while (stack.length) {
                var current = stack.pop();
                if (current === without || seen[current]) {
                    continue;
                }
                seen[current] = true;
                next[current].forEach(function (to) { stack.push(to); });
            }
            return seen;
        }

        var entries = slugs.filter(function (slug) { return !origins[slug].length; });
        var kind = {};
        slugs.forEach(function (slug) {
            if (!next[slug].length) {
                kind[slug] = 'final';
            } else if (!origins[slug].length) {
                kind[slug] = 'entry';
            } else {
                var around = reach(entries, slug);
                var required = Object.keys(reach([slug], '')).some(function (other) {
                    return !next[other].length && !around[other];
                });
                kind[slug] = required ? 'required' : 'optional';
            }
        });

        // Profundidad desde la entrada por el camino más largo, solo por aristas hacia delante.
        var state = {};
        var back = {};
        var post = [];
        var depth = {};
        function visit(slug) {
            state[slug] = 1;
            next[slug].forEach(function (to) {
                if (!state[to]) {
                    visit(to);
                } else if (state[to] === 1) {
                    back[slug + '>' + to] = true;
                }
            });
            state[slug] = 2;
            post.push(slug);
        }
        slugs.forEach(function (slug) {
            if (kind[slug] === 'entry' && !state[slug]) {
                visit(slug);
                depth[slug] = 0;
            }
        });
        post.reverse().forEach(function (slug) {
            next[slug].forEach(function (to) {
                if (!back[slug + '>' + to]) {
                    depth[to] = Math.max(depth[to] || 0, (depth[slug] || 0) + 1);
                }
            });
        });

        var entry = [];
        var final = [];
        var orphans = [];
        var levels = {};
        slugs.forEach(function (slug) {
            if (kind[slug] === 'entry') {
                entry.push(slug);
            } else if (kind[slug] === 'final') {
                final.push(slug);
            } else if (depth[slug] !== undefined) {
                (levels[depth[slug]] = levels[depth[slug]] || []).push(slug);
            } else {
                orphans.push(slug);
            }
        });
        var process = Object.keys(levels).map(Number).sort(function (a, b) { return a - b; }).map(function (level) {
            return levels[level];
        });
        return {
            kind: kind,
            stages: [
                { key: 'entry', cols: entry.length ? [entry] : [] },
                { key: 'process', cols: process },
                { key: 'final', cols: final.length ? [final] : [] },
                { key: 'orphan', cols: orphans.length ? [orphans] : [] }
            ].filter(function (stage) { return stage.cols.length; })
        };
    }

    function el(tag, className, content) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (content) {
            node.textContent = content;
        }
        return node;
    }

    function pillFor(status) {
        var pill = el('span', 'jl-pill' + (status.color ? ' jl-tone--' + status.color.replace(/[^a-z0-9_-]/gi, '') : ''));
        if (status.icon) {
            var icon = el('span', 'dashicons dashicons-' + status.icon.replace(/[^a-z0-9_-]/gi, ''));
            icon.setAttribute('aria-hidden', 'true');
            pill.appendChild(icon);
        }
        pill.appendChild(document.createTextNode(status.name));
        return pill;
    }

    function renderNodes(model, layout) {
        var byslug = {};
        model.forEach(function (status) { byslug[status.slug] = status; });
        var active = document.activeElement && document.activeElement.closest ? document.activeElement.closest('[data-jl-node]') : null;
        var focused = active && stagesEl.contains(active) ? active.getAttribute('data-jl-node') : null;
        stagesEl.textContent = '';

        layout.stages.forEach(function (stage) {
            var box = el('div', 'jl-stage jl-stage--' + stage.key);
            box.appendChild(el('h3', 'jl-stage__title', (text.stages || {})[stage.key] || stage.key));
            var cols = el('div', 'jl-stage__cols');
            stage.cols.forEach(function (col) {
                var column = el('ul', 'jl-stage__col');
                col.forEach(function (slug) {
                    var status = byslug[slug];
                    var item = el('li', 'jl-node' + (layout.kind[slug] === 'optional' ? ' jl-node--optional' : ''));
                    item.setAttribute('data-jl-node', slug);
                    var main = el('span', 'jl-node__main');
                    var button = el('button', 'jl-node__button');
                    button.type = 'button';
                    button.appendChild(pillFor(status));
                    button.appendChild(el('span', 'screen-reader-text', ', ' + (text.edit || '')));
                    button.setAttribute('aria-describedby', 'jl-node-to-' + slug);
                    main.appendChild(button);
                    if (layout.kind[slug] === 'optional') {
                        main.appendChild(el('span', 'jl-tag', text.optional || ''));
                    }
                    item.appendChild(main);
                    var targets = status.next.map(function (to) { return byslug[to].name; });
                    var summary = el('span', 'jl-node__to', targets.length ? (text.canGoTo || '%s').replace('%s', targets.join(', ')) : text.finalStatus || '');
                    summary.id = 'jl-node-to-' + slug;
                    item.appendChild(summary);
                    column.appendChild(item);
                });
                cols.appendChild(column);
            });
            box.appendChild(cols);
            stagesEl.appendChild(box);
        });

        if (focused) {
            var again = stagesEl.querySelector('[data-jl-node="' + focused + '"] button');
            if (again) {
                again.focus({ preventScroll: true });
            }
        }
    }

    // Un trazo con las esquinas redondeadas a partir de sus vértices.
    function roundedPath(points, radius) {
        var d = 'M' + points[0][0] + ',' + points[0][1];
        for (var i = 1; i < points.length - 1; i++) {
            var prev = points[i - 1];
            var cur = points[i];
            var nxt = points[i + 1];
            var lenIn = Math.abs(cur[0] - prev[0]) + Math.abs(cur[1] - prev[1]);
            var lenOut = Math.abs(nxt[0] - cur[0]) + Math.abs(nxt[1] - cur[1]);
            var r = Math.min(radius, lenIn / 2, lenOut / 2);
            if (r < 0.5) {
                d += 'L' + cur[0] + ',' + cur[1];
                continue;
            }
            var sx = Math.sign(cur[0] - prev[0]);
            var sy = Math.sign(cur[1] - prev[1]);
            var ex = Math.sign(nxt[0] - cur[0]);
            var ey = Math.sign(nxt[1] - cur[1]);
            d += 'L' + (cur[0] - sx * r) + ',' + (cur[1] - sy * r) + 'Q' + cur[0] + ',' + cur[1] + ' ' + (cur[0] + ex * r) + ',' + (cur[1] + ey * r);
        }
        var last = points[points.length - 1];
        return d + 'L' + last[0] + ',' + last[1];
    }

    function svgEl(tag, attrs) {
        var node = document.createElementNS(SVG_NS, tag);
        Object.keys(attrs).forEach(function (name) { node.setAttribute(name, attrs[name]); });
        return node;
    }

    /**
     * Las flechas: una por destino. Entre columnas contiguas, por el canal entre ellas; si saltan
     * columnas (o vuelven atrás), bajan a un carril bajo las etapas y suben por el canal de la
     * columna de llegada, así no cruzan ninguna pastilla.
     */
    function drawArrows(model) {
        while (svg.firstChild) {
            svg.removeChild(svg.firstChild);
        }
        flow.arrows = [];
        var colEls = Array.prototype.slice.call(stagesEl.querySelectorAll('.jl-stage__col'));
        var colOf = {};
        var pillOf = {};
        colEls.forEach(function (col, index) {
            Array.prototype.forEach.call(col.querySelectorAll('[data-jl-node]'), function (item) {
                var slug = item.getAttribute('data-jl-node');
                colOf[slug] = index;
                pillOf[slug] = item.querySelector('.jl-pill');
            });
        });

        var edges = [];
        model.forEach(function (status) {
            status.next.forEach(function (to) {
                if (colOf[status.slug] !== undefined && colOf[to] !== undefined) {
                    edges.push({ from: status.slug, to: to, a: colOf[status.slug], b: colOf[to] });
                }
            });
        });

        // Carriles bajo las etapas para los saltos y los retornos, los más cortos más arriba.
        var far = edges.filter(function (edge) { return edge.b !== edge.a + 1; });
        far.sort(function (x, y) { return Math.abs(x.b - x.a) - Math.abs(y.b - y.a); });
        var LANE = 9;
        canvas.style.paddingBottom = far.length ? 14 + far.length * LANE + 'px' : '';

        var origin = canvas.getBoundingClientRect();
        var rel = function (rect) {
            return { left: rect.left - origin.left, right: rect.right - origin.left, top: rect.top - origin.top, bottom: rect.bottom - origin.top };
        };
        var cols = colEls.map(function (col) { return rel(col.getBoundingClientRect()); });
        var stagesBottom = rel(stagesEl.getBoundingClientRect()).bottom;
        var width = origin.width;
        var height = origin.height;
        svg.setAttribute('width', width);
        svg.setAttribute('height', height);
        svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);

        // Los canales: el hueco entre dos columnas, repartido entre las flechas que lo usan.
        var uses = {};
        edges.forEach(function (edge) {
            edge.out = edge.a;
            edge.into = edge.b - 1;
            [edge.out, edge.into].filter(function (gap, i, all) { return all.indexOf(gap) === i; }).forEach(function (gap) {
                (uses[gap] = uses[gap] || []).push(edge);
            });
        });
        function channel(gap, edge) {
            var from = gap < 0 ? cols[0].left - 22 : cols[gap].right;
            var to = gap >= cols.length - 1 ? cols[cols.length - 1].right + 22 : cols[gap + 1].left;
            var users = uses[gap];
            var x = from + ((to - from) * (users.indexOf(edge) + 1)) / (users.length + 1);
            return Math.max(4, Math.min(width - 4, Math.round(x)));
        }

        var HEAD = 8;
        edges.forEach(function (edge) {
            var source = rel(pillOf[edge.from].getBoundingClientRect());
            var target = rel(pillOf[edge.to].getBoundingClientRect());
            var x0 = Math.round(source.right);
            var y0 = Math.round((source.top + source.bottom) / 2);
            var x1 = Math.round(target.left) - 1;
            var y1 = Math.round((target.top + target.bottom) / 2);
            var isFar = edge.b !== edge.a + 1;
            var points;
            if (!isFar) {
                var cx = channel(edge.out, edge);
                points = y0 === y1 ? [[x0, y0], [x1 - HEAD, y1]] : [[x0, y0], [cx, y0], [cx, y1], [x1 - HEAD, y1]];
            } else {
                var laneY = Math.round(stagesBottom) + 12 + far.indexOf(edge) * LANE;
                var xOut = channel(edge.out, edge);
                var xIn = channel(edge.into, edge);
                points = [[x0, y0], [xOut, y0], [xOut, laneY], [xIn, laneY], [xIn, y1], [x1 - HEAD, y1]];
            }
            var group = svgEl('g', { 'class': 'jl-arrow' + (isFar ? ' is-far' : '') });
            group.appendChild(svgEl('path', { 'class': 'jl-arrow__line', d: roundedPath(points, 8) }));
            group.appendChild(svgEl('path', { 'class': 'jl-arrow__head', d: 'M' + x1 + ',' + y1 + 'L' + (x1 - HEAD) + ',' + (y1 - 4.5) + 'L' + (x1 - HEAD) + ',' + (y1 + 4.5) + 'Z' }));
            svg.appendChild(group);
            flow.arrows.push({ from: edge.from, to: edge.to, group: group });
        });
    }

    /** Resalta las flechas del estado señalado (o ninguna) y atenúa las demás. */
    function highlight(slug) {
        map.classList.toggle('has-focus', !!slug);
        flow.arrows.forEach(function (arrow) {
            var on = !!slug && (arrow.from === slug || arrow.to === slug);
            arrow.group.classList.toggle('is-active', on);
            if (on) {
                svg.appendChild(arrow.group); // por encima de las atenuadas
            }
        });
    }

    function currentTarget() {
        return flow.hover || flow.focus || null;
    }

    function renderFlowmap() {
        flow.queued = false;
        var model = readModel();
        var layout = layoutFlow(model);
        renderNodes(model, layout);

        // Primero se prueba en horizontal; si no cabe en el ancho disponible, las etapas se apilan
        // y la información queda en el texto de cada estado (sin flechas).
        map.classList.add('is-diagram');
        canvas.style.paddingBottom = '';
        var fits = stagesEl.scrollWidth <= stagesEl.clientWidth + 1;
        map.classList.toggle('is-diagram', fits);
        // SVGElement no tiene la propiedad hidden: va el atributo.
        if (fits) {
            svg.removeAttribute('hidden');
        } else {
            svg.setAttribute('hidden', '');
        }
        if (fits) {
            drawArrows(model);
            highlight(currentTarget());
        } else {
            flow.arrows = [];
        }
        flow.width = canvas.clientWidth;
    }

    function scheduleFlowmap() {
        if (!flow || !flow.ready || flow.queued) {
            return;
        }
        flow.queued = true;
        window.requestAnimationFrame(renderFlowmap);
    }

    if (map && stagesEl && canvas && svg) {
        var hint = map.querySelector('[data-jl-flowmap-hint]');
        if (hint && text.liveHint) {
            hint.textContent = text.liveHint;
        }
        flow.ready = true;
        renderFlowmap();

        var nodeOf = function (target) {
            return target && target.closest ? target.closest('[data-jl-node]') : null;
        };
        stagesEl.addEventListener('mouseover', function (event) {
            var node = nodeOf(event.target);
            flow.hover = node ? node.getAttribute('data-jl-node') : null;
            highlight(currentTarget());
        });
        stagesEl.addEventListener('mouseout', function (event) {
            if (!nodeOf(event.relatedTarget)) {
                flow.hover = null;
                highlight(currentTarget());
            }
        });
        stagesEl.addEventListener('focusin', function (event) {
            var node = nodeOf(event.target);
            flow.focus = node ? node.getAttribute('data-jl-node') : null;
            highlight(currentTarget());
        });
        stagesEl.addEventListener('focusout', function () {
            flow.focus = null;
            highlight(currentTarget());
        });
        // Un clic en un estado del diagrama lleva a su tarjeta, abierta, en la lista de abajo.
        stagesEl.addEventListener('click', function (event) {
            var node = nodeOf(event.target);
            var card = node && list.querySelector('[data-jl-status][data-slug="' + node.getAttribute('data-jl-node') + '"]');
            if (!card) {
                return;
            }
            setOpen(card, true);
            card.scrollIntoView({ block: 'nearest', behavior: reduceMotion && reduceMotion.matches ? 'auto' : 'smooth' });
            var field = card.querySelector('input[name$="[label]"]');
            if (field) {
                field.focus({ preventScroll: true });
            }
        });

        var statusesForm = root.querySelector('#jl-statuses-form');
        statusesForm.addEventListener('input', scheduleFlowmap);
        statusesForm.addEventListener('change', scheduleFlowmap);
        window.addEventListener('resize', function () {
            if (canvas.clientWidth !== flow.width) {
                scheduleFlowmap();
            }
        });
        window.addEventListener('load', scheduleFlowmap);
        if (window.ResizeObserver) {
            new window.ResizeObserver(function () {
                if (canvas.clientWidth !== flow.width) {
                    scheduleFlowmap();
                }
            }).observe(canvas);
        }
    }

    // ------------------------------------------------------------ restaurar

    var reset = root.querySelector('[data-jl-confirm="reset"]');
    if (reset) {
        reset.addEventListener('submit', function (event) {
            if (text.confirmReset && !window.confirm(text.confirmReset)) {
                event.preventDefault();
            }
        });
    }
})();
